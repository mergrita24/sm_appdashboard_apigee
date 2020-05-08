<?php

namespace Drupal\sm_appdashboard_apigee;

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

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides useful tasks and functions.
 */
class AppsDashboardStorageService implements AppsDashboardStorageServiceInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DefaultService object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function labels() {
    $labels = [
      'labelDisplayName' => t('App Display Name'),
      'labelEmail' => t('Developer Email'),
      'labelCompany' => t('Company'),
      'labelStatus' => t('Overall App Status'),
      'labelOnwerActive' => t('Active user in the site?'),
      'labelDateTimeCreated' => t('App Date/Time Created'),
      'labelDateTimeModified' => t('App Date/Time Modified'),
      'labelOperations' => t('Operations'),
    ];

    return $labels;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllAppDetails() {
    $apps = [];

    $devApps_storage = $this->entityTypeManager->getStorage('developer_app');
    $devApps = $devApps_storage->loadMultiple();

    if ($teamApps_storage = $this->entityTypeManager->getStorage('team_app')) {
      $teamApps = $teamApps_storage->loadMultiple();
      $apps = array_merge($devApps, $teamApps);
    }

    return $apps;
  }

  /**
   * {@inheritdoc}
   */
  public function getAppDetailsById($type, $id) {

    if (isset($type) && isset($id)) {
      $app = $this->entityTypeManager->getStorage($type)->load($id);
    }

    return $app;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiProducts($app) {
    $data_apiProducts = [];

    $appCredentials = $app->getCredentials();

    foreach ($appCredentials[0]->getApiProducts() as $apiProduct) {
      $data_apiProducts[] = [
        $apiProduct->getApiProduct(),
        $apiProduct->getStatus(),
      ];
    }

    return $data_apiProducts;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverallStatus($app) {
    $appCredentials = $app->getCredentials();

    $appStatus = $app->getStatus();
    $appCredStatus = $appCredentials[0]->getStatus();

    static $statuses;

    if (!isset($statuses)) {
      $statuses = [
        'approved' => 0,
        'pending' => 1,
        'revoked' => 2,
      ];
    }

    $appStatus = (array_key_exists($app->getStatus(), $statuses) ? $statuses[$app->getStatus()] : 0);
    $appCredStatus = (array_key_exists($appCredentials[0]->getStatus(), $statuses) ? $statuses[$appCredentials[0]->getStatus()] : 0);
    $appOverallStatus = max($appStatus, $appCredStatus);

    if ($appOverallStatus < 2) {
      foreach ($appCredentials[0]->getApiProducts() as $api_product) {
        if (!array_key_exists($api_product->getStatus(), $statuses)) {
          continue;
        }

        $appOverallStatus = max($appOverallStatus, $statuses[$api_product->getStatus()]);

        if ($appOverallStatus == 2) {
          break;
        }
      }
    }

    $arrStatusSearch = array_search($appOverallStatus, $statuses);

    return $arrStatusSearch;
  }

  /**
   * {@inheritdoc}
   */
  public function startsWith($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
  }

}