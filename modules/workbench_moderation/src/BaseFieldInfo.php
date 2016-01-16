<?php

/**
 * @file
 * Contains \Drupal\workbench_moderation\BaseFieldInfo.
 */

namespace Drupal\workbench_moderation;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a class for handling moderation state base fields.
 */
class BaseFieldInfo {

  /**
   * Adds base field info to an entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   Entity type for adding base fields to.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   New fields added by moderation state.
   */
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    if ($entity_type->isRevisionable()) {
      $fields = [];
      // @todo write a test for this.
      $fields['moderation_state'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Moderation state'))
        ->setDescription(t('The moderation state of this piece of content.'))
        ->setSetting('target_type', 'moderation_state')
        ->setTargetEntityTypeId($entity_type->id())
        ->setRevisionable(TRUE)
        // @todo write a test for this.
        ->setDisplayOptions('view', [
          'label' => 'hidden',
          'type' => 'string',
          'weight' => -5,
        ])
        // @todo write a custom widget/selection handler plugin instead of
        // manual filtering?
        ->setDisplayOptions('form', [
          'type' => 'moderation_state_default',
          'weight' => 5,
          'settings' => [],
        ])
        ->addConstraint('ModerationState', [])
        ->setDisplayConfigurable('form', FALSE)
        ->setDisplayConfigurable('view', TRUE);
      return $fields;
    }
    return [];
  }

}
