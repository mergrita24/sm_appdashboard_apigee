<?php

namespace Drupal\sm_appdashboard_apigee\Controller;

/**
 * @file
 * Copyright (C) 2020  Stratus Meridian LLC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller for the Apps Dashboard view and list pages.
 */
class AppsDashboardController extends ControllerBase {

  /**
   * AppsDashboardStorageServiceInterface definition.
   *
   * @var Drupal\sm_appdashboard_apigee\AppsDashboardStorageServiceInterface
   */
  protected $appsDashboardStorage;

  /**
   * The Form Builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->appsDashboardStorage = $container->get('sm_appsdashboard_apigee.appsdashboard_storage');
    $instance->formBuilder = $container->get('form_builder');
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function listApps() {
    // Define if there is searchKey.
    if ($this->requestStack->getCurrentRequest()->get('search')) {
      $searchKey = $this->requestStack->getCurrentRequest()->get('search');
      $searchType = $this->requestStack->getCurrentRequest()->get('search_type');
    }

    // Define Table Headers.
    $labelAppDetails = $this->appsDashboardStorage->labels();

    if (isset($searchKey)) {
      $apps = $this->appsDashboardStorage->searchBy($searchKey, $searchType);
    }
    else {
      // Retrieve Apps Details (Developer and Team Apps).
      $apps = $this->appsDashboardStorage->getAllAppDetails();
    }

    // Pass App Details into variables.
    $appDetails = [];

    foreach ($apps as $appKey => $app) {
      if ($app->getEntityTypeId() == 'developer_app') {
        // Set Developer Apps owner active data.
        $ownerEntity = $app->getOwner();

        if ($ownerEntity) {
          $appOwnerActive = ($ownerEntity->get('status')->getValue()[0]['value'] == 1 ? $this->t('yes') : $this->t('no'));
        }
        else {
          $appOwnerActive = $this->t('no');
        }

        // Set Developer Apps email address data.
        if ($app->getOwnerId()) {
          if ($ownerEntity) {
            $appDeveloperEmail = ($ownerEntity->getEmail() ? $ownerEntity->getEmail() : '');
          }
        }
        else {
          $appDeveloperEmail = $app->getCreatedBy();
        }

        $appCompany = '';
      }
      else {
        // Set Team Apps company name.
        $appDeveloperEmail = '';
        $appCompany = $app->getCompanyName();
      }

      // Get App Overall Status.
      $appOverallStatus = $this->appsDashboardStorage->getOverallStatus($app);

      // Setup actions (dropdown).
      $view_url = Url::fromRoute('apps_dashboard.view', [
        'apptype' => $app->getEntityTypeId(),
        'appid' => $appKey,
      ]);

      $edit_url = Url::fromRoute('apps_dashboard.edit', [
        'apptype' => $app->getEntityTypeId(),
        'appid' => $appKey,
      ]);

      $drop_button = [
        '#type' => 'dropbutton',
        '#links' => [
          '#view' => [
            'title' => $this->t('View'),
            'url' => $view_url,
          ],
          '#edit' => [
            'title' => $this->t('Edit'),
            'url' => $edit_url,
          ],
        ],
      ];

      // App Details array push to variables.
      array_push($appDetails, [
        'fieldDisplayName' => $app->getDisplayName() . ' [Internal Name: ' . $app->getName() . ']',
        'fieldEmail' => $appDeveloperEmail,
        'fieldCompany' => $appCompany,
        'fieldStatus' => $appOverallStatus,
        'fieldOnwerActive' => $appOwnerActive,
        'fieldDateTimeCreated' => $app->getCreatedAt()->format('l, M. d, Y H:i'),
        'fieldDateTimeModified' => $app->getlastModifiedAt()->format('l, M. d, Y H:i'),
        'actions' => [
          'data' => $drop_button,
        ],
      ]);
    }

    // Construct Pager.
    $appDetails = $this->appsDashboardStorage->constructPager($appDetails, 10);

    // Construct Table Sort.
    $appDetails = $this->appsDashboardStorage->constructSort($appDetails, $labelAppDetails);

    // Merge into one array variable.
    $arrApps = [
      'labelAppDetails' => $labelAppDetails,
      'appDetails' => $appDetails,
    ];

    $form = [
      'search__apps_dashboard' => $this->formBuilder->getForm('\Drupal\sm_appdashboard_apigee\Form\AppDetailsSearchForm'),
      'table__apps_dashboard' => [
        '#type' => 'table',
        '#header' => $arrApps['labelAppDetails'],
        '#rows' => $arrApps['appDetails'],
        '#empty' => $this->t('No data found'),
      ],
      'pager__apps_dashboard' => [
        '#type' => 'pager',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewApp($apptype, $appid) {
    if (!isset($apptype) || !isset($appid)) {
      $this->messenger()->addError($this->t('There are errors encountered upon viewing the App Details.'));
      $path = Url::fromRoute('apps_dashboard.list', [])->toString();
      $response = new RedirectResponse($path);
      $response->send();
    }

    // Load App Deails.
    $app = $this->appsDashboardStorage->getAppDetailsById($apptype, $appid);

    if ($app->getEntityTypeId() == 'developer_app') {
      // Set Developer Apps owner active data.
      $ownerEntity = $app->getOwner();

      if ($ownerEntity) {
        $appOwnerActive = ($ownerEntity->get('status')->getValue()[0]['value'] == 1 ? $this->t('yes') : $this->t('no'));
      }
      else {
        $appOwnerActive = $this->t('no');
      }

      // Set Developer Apps email address data.
      if ($app->getOwnerId()) {
        if ($ownerEntity) {
          $appDeveloperEmail = ($ownerEntity->getEmail() ? $ownerEntity->getEmail() : '');
        }
      }
      else {
        $appDeveloperEmail = $app->getCreatedBy();
      }

      $appCompany = '';
    }
    else {
      // Set Team Apps company name.
      $appDeveloperEmail = '';
      $appCompany = $app->getCompanyName();
    }

    // Get App Credentials and API Products.
    $apiProducts = $this->appsDashboardStorage->getApiProducts($app);

    // Get App Overall Status.
    $appOverallStatus = $this->appsDashboardStorage->getOverallStatus($app);

    $data_apiProducts = [];

    foreach ($apiProducts as $apiProduct) {
      $data_apiProducts[] = [
        [
          'data' => $apiProduct[0],
          'header' => TRUE,
        ],
        $apiProduct[1],
      ];
    }

    // Plotting App Details into Table.
    $data = [
      [
        ['data' => 'App Type', 'header' => TRUE],
        $apptype,
      ],
      [
        ['data' => 'App Display Name', 'header' => TRUE],
        $app->getDisplayName(),
      ],
      [
        ['data' => 'Internal Name', 'header' => TRUE],
        $app->getName(),
      ],
      [
        ['data' => 'Developer Email Address', 'header' => TRUE],
        $appDeveloperEmail,
      ],
      [
        ['data' => 'Company', 'header' => TRUE],
        $appCompany,
      ],
      [
        ['data' => 'Overall App Status', 'header' => TRUE],
        $appOverallStatus,
      ],
      [
        ['data' => 'Active User in the site?', 'header' => TRUE],
        $appOwnerActive,
      ],
      [
        ['data' => 'App Date/Time Created', 'header' => TRUE],
        $app->getCreatedAt()->format('l, M. d, Y H:i'),
      ],
      [
        ['data' => 'App Date/Time Modified', 'header' => TRUE],
        $app->getLastModifiedAt()->format('l, M. d, Y H:i'),
      ],
      [
        ['data' => 'Modified by', 'header' => TRUE],
        $app->getLastModifiedBy(),
      ],
    ];

    $return_url = Url::fromRoute('apps_dashboard.list');
    $edit_url = Url::fromRoute('apps_dashboard.edit', [
      'apptype' => $app->getEntityTypeId(),
      'appid' => $appid,
    ]);

    $display = [
      'details__app_details' => [
        '#type' => 'details',
        '#title' => $this->t('App Details'),
        '#open' => TRUE,
        'table__app_details' => [
          '#type' => 'table',
          '#rows' => $data,
        ],
      ],
      'details__api_products' => [
        '#type' => 'details',
        '#title' => $this->t('API Products'),
        '#open' => TRUE,
        'apiProducts' => [
          '#type' => 'table',
          '#rows' => $data_apiProducts,
          '#attributes' => [
            'class' => [
              'table__view__apps_dashboard__api_products',
            ],
          ],
        ],
      ],
      'edit__action' => [
        '#type' => 'link',
        '#title' => $this->t('Edit'),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
        '#url' => $edit_url,
      ],
      'list__action' => [
        '#type' => 'link',
        '#title' => $this->t('Back'),
        '#attributes' => [
          'class' => [
            'button',
          ],
        ],
        '#url' => $return_url,
      ],
    ];

    return $display;
  }

}
