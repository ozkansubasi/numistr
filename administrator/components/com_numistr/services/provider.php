<?php
defined('_JEXEC') or die;

use Joomla\CMS\Router\ApiRouter;

/**
 * Numistr API route provider (v1)
 * Registers read-only routes for MVP. Write/auth endpoints are stubbed.
 */
return [
  'api' => function (ApiRouter $router) {
    // Core catalog
    $router->createCRUDRoutes('v1/variants', 'variants', ['public' => ['get', 'getItem']]);
    $router->createCRUDRoutes('v1/mints',    'mints',    ['public' => ['get', 'getItem']]);
    $router->createCRUDRoutes('v1/regions',  'regions',  ['public' => ['get', 'getItem']]);

    // Images: item-meta endpoint (URLs are provided via existing secure pipeline)
    $router->createCRUDRoutes('v1/images',   'images',   ['public' => ['getItem']]);

    // Search proxy (collection-style GET)
    $router->createCRUDRoutes('v1/search',   'search',   ['public' => ['get']]);

    // Feed (home screen)
    $router->createCRUDRoutes('v1/feed',     'feed',     ['public' => ['get']]);

    // Auth-required (skeleton only)
    $router->createCRUDRoutes('v1/me',        'me',       ['public' => ['get']]);
    $router->createCRUDRoutes('v1/favorites', 'favorites',[
      'public' => ['get'], // list favorites (stub)
      // POST/DELETE exist but will return 501 until enabled
      'post'   => true,
      'delete' => true,
    ]);

    // Vision (asynchronous; only skeleton)
    $router->createCRUDRoutes('v1/vision/match', 'visionmatch', [
      'public' => ['post', 'get'], // POST -> job create, GET -> job status (both stub)
    ]);

    // Billing / IAP receipt (skeleton)
    $router->createCRUDRoutes('v1/billing/receipt', 'billingreceipt', [
      'public' => ['post'],
    ]);
  }
];
