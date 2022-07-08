<?php

namespace Drupal\https_www_redirects\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber for handling redirects
 */
class RedirectSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private $config;

  /**
   * The instantiated Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  private $cache;

  /**
   * The Request URI Service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * HttpsRedirectSubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   A cache backend used to store configuration.
   * @param Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request URI service to get request URL.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache, RequestStack $request_stack) {
    $this->config = $config_factory;
    $this->cache = $cache;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  public function onRequest(RequestEvent $event) {
    // Match the pattern to see if the address is that of a localhost.
    // Avoids execution if host matches or relates to 127.0.0.1 and so on.
    $hostPattern = "/^((https?|http?|ftp)\:\/\/)?(127\.0{0,3}\.0{0,3}.0{0,2}1|localhost)?(:\d+)?$/";

    // Regex pattern to determine if URI contains 'www'
    $wwwPattern = "~\b(?:https?://)?(www\.[^.\s]+\.[^/\s]+|\d+\.\d+\.\d+\.\d+)~";

    // Load the module settings
    $forceHttps = $this->config->get('https_www_redirects.settings')->get('force_https');
    $forceWww = $this->config->get('https_www_redirects.settings')->get('force_www');
    $host = $this->request->getSchemeAndHttpHost();

    // Don't continue if neither option is set
    if (!(bool)$forceHttps && !(bool)$forceWww) {
      return;
    }

    if (!preg_match($hostPattern, $host)) {
      $request = $event->getRequest();
      $hostOnly = $this->request->getHost();
      $updateSecureUrl = false;
      $updateWwwUrl = false;

      // Check for forced HTTPS
      if ($forceHttps) {

        // Check against the bypass list first
        $bypassHttpsHosts = $this->config->get('https_www_redirects.settings')->get('bypass_https_hosts');
        if (!empty($bypassHttpsHosts)) {
          if (in_array($hostOnly, $bypassHttpsHosts)) {
            return;
          }
        }

        // If not bypassed, continue
        if (!$request->isSecure()) {
          $updateSecureUrl = true;
        } else {
          if (!(bool)$forceWww) {
            return;
          }
        }
      }

      // Check for forced WWW
      if ($forceWww) {

        // Check against the bypass list first
        $bypassWwwHosts = $this->config->get('https_www_redirects.settings')->get('bypass_www_hosts');
        if (!empty($bypassWwwHosts)) {
          if (in_array($hostOnly, $bypassWwwHosts)) {
            return;
          }
        }

        // If not bypassed, continue
        if (preg_match($wwwPattern, $host) && !$updateSecureUrl) {
          return;
        }
        $updateWwwUrl = true;
      }

      $url = Url::fromUri("internal:{$request->getPathInfo()}");
      $url->setOption('absolute', TRUE)
        ->setOption('external', FALSE)
        ->setOption('query', $request->query->all());

      $status = $this->getRedirectStatus($event);

      if ($updateSecureUrl) {
        $url->setOption('https', TRUE);
        $url = $this->createSecureUrl($url->toString());
      }
      if ($updateWwwUrl) {
        $url = $this->createWwwUrl(is_string($url) ? $url : $url->toString());
      }
      $response = new TrustedRedirectResponse($url, $status);
      $event->setResponse($response);
    }
  }

  /**
   * Determines proper redirect status based on request method.
   */
  public function getRedirectStatus(RequestEvent $event) {
    return $event->getRequest()->isMethodCacheable() ? RedirectResponse::HTTP_MOVED_PERMANENTLY : RedirectResponse::HTTP_PERMANENTLY_REDIRECT;
  }

  /**
   * Rewrites a URL to a secure base URL.
   *
   * @param $url
   * @return array|string|string[]
   */
  public function createSecureUrl($url) {
    global $base_path, $base_secure_url;

    // Set the request url to use secure base URL in place of base path.
    if (str_starts_with($url, $base_path)) {
      $base_url = $this->config->get('base_url') ?: $base_secure_url;
      return substr_replace($url, $base_url, 0, strlen($base_path) - 1);
    }
    // Or if a different domain is being used, forcibly rewrite to HTTPS.
    return str_replace('http://', 'https://', $url);
  }

  /**
   * Rewrites a URL to a URL containing "www"
   *
   * @param $url
   * @return string
   */
  public function createWwwUrl($url) {
    $urlParsed = parse_url($url);

    // Parse out URL
    $newUrl = str_replace($urlParsed['scheme'] . '://', '', $url);
    $newUrl = str_replace($urlParsed['host'], '', $newUrl);

    // Put it back together with "www"
    return $urlParsed['scheme'] . '://www.' . $urlParsed['host'] . $newUrl;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    return $events;
  }
}
