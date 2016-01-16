<?php
/**
 * @file
 * Contains
 * \Drupal\scheduled_updates\Plugin\UpdateRunner\IndependentUpdateRunner.
 */


namespace Drupal\scheduled_updates\Plugin\UpdateRunner;


use Drupal\scheduled_updates\Entity\ScheduledUpdate;
use Drupal\scheduled_updates\Plugin\BaseUpdateRunner;
use Drupal\scheduled_updates\Plugin\UpdateRunnerInterface;

/**
 * The default Embedded Update Runner.
 *
 * @UpdateRunner(
 *   id = "default_independent",
 *   label = @Translation("Default"),
 *   description = @Translation("Updates are created directly."),
 *   update_types = {"independent"}
 * )
 */
class IndependentUpdateRunner extends BaseUpdateRunner implements UpdateRunnerInterface {

  /**
   * Get all scheduled updates that referencing entities via Entity Reference
   * Field
   *
   *  @return ScheduledUpdate[]
   */
  protected function getReferencingUpdates() {
    $updates = [];
    $update_ids = $this->getReadyUpdateIds();
    foreach ($update_ids as $update_id) {
      $updates[] = [
        'update_id' => $update_id,
        'entity_type' => $this->updateEntityType(),
      ];
    }
    return $updates;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllUpdates() {
    return $this->getReferencingUpdates();
  }
}
