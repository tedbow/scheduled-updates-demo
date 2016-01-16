<?php

/**
 * @file
 * Contains \Drupal\scheduled_updates\ScheduledUpdateTypeInterface.
 */

namespace Drupal\scheduled_updates;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Scheduled update type entities.
 */
interface ScheduledUpdateTypeInterface extends ConfigEntityInterface {
  // Add get/set methods for your configuration properties here.
  const REFERENCER = 1;

  const REFERENCED = 2;

  public function getUpdateEntityType();

  public function isEmbeddedType();

  public function isIndependentType();

  /**
   * @return array
   */
  public function getFieldMap();

  /**
   * @param array $field_map
   */
  public function setFieldMap($field_map);

  public function cloneField($new_field);

  /**
   * Return update runner settings.
   *
   * @return array
   */
  public function getUpdateRunnerSettings();


}
