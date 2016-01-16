<?php
/**
 * @file
 * Contains \Drupal\scheduled_updates\FieldUtilsTrait.
 */


namespace Drupal\scheduled_updates;


use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;

trait FieldUtilsTrait {


  protected function getDestinationFieldsOptions($source_field) {
    $destination_fields = $this->getDestinationFields($source_field);
    $options = [];
    foreach ($destination_fields as $field_id => $destination_field) {
      $options[$field_id] = $destination_field->getName();
    }
    return $options;
  }

  /**
   * Return all fields that can be used as destinations fields.
   *
   * @todo Move off of form class
   *
   * @param \Drupal\field\Entity\FieldConfig $source_field
   *
   * @return FieldStorageDefinitionInterface[]
   */
  protected function getDestinationFields(FieldConfig $source_field = NULL) {
    $destination_fields = [];

    $fields = $this->FieldManager()->getFieldStorageDefinitions($this->getEntity()->getUpdateEntityType());
    foreach ($fields as $field_id => $field) {
      if ($this->isDestinationFieldCompatible($field, $source_field)) {
        $destination_fields[$field_id] = $field;
      }
    }
    return $destination_fields;
  }

  /**
   * Get Fields that can used as a destination field for this type.
   *
   * @todo Move off of form class
   *
   * @param string $type
   *
   * @return array
   */
  protected function getMatchingFieldTypes($type) {
    // @todo which types can be interchanged
    return [$type];
  }

  /**
   * Check if a field on the entity type to update is a possible destination field.
   *
   * @todo Should this be on our FieldManager service?
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *  Field definition on entity type to update to check.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $source_field
   *  Source field to check compatibility against. If none then check generally.
   *
   * @return bool
   */
  protected function isDestinationFieldCompatible(FieldStorageDefinitionInterface $definition, FieldDefinitionInterface $source_field = NULL) {
    // @todo Create field definition wrapper class to treat FieldDefinitionInterface and FieldStorageDefinitionInterface the same.
    if ($definition instanceof BaseFieldDefinition && $definition->isReadOnly()) {
      return FALSE;
    }
    // Don't allow updates on updates!
    if ($definition->getType() == 'entity_reference') {
      if ($definition->getSetting('target_type') == 'scheduled_update') {
        return FALSE;
      }
    }

    if ($source_field) {
      $matching_types = $this->getMatchingFieldTypes($source_field->getType());
      if (!in_array($definition->getType(), $matching_types)) {
        return FALSE;
      }
      // Check cardinality
      $destination_cardinality = $definition->getCardinality();
      $source_cardinality = $source_field->getFieldStorageDefinition()->getCardinality();
      // $destination_cardinality is unlimited. It doesn't matter what source is.
      if ($destination_cardinality != -1) {
        if ($source_cardinality == -1) {
          return FALSE;
        }
        if ($source_cardinality > $destination_cardinality) {
          return FALSE;
        }
      }


      switch($definition->getType()) {
        case 'entity_reference':
          // Entity reference field must match entity target types.
          if ($definition->getSetting('target_type') != $source_field->getSetting('target_type')) {
            return FALSE;
          }
          // @todo Check bundles
          break;
        // @todo Other type specific conditions?
      }

    }
    return TRUE;
  }

  /**
   * @param \Drupal\scheduled_updates\ScheduledUpdateTypeInterface $updateType
   *
   * @todo Move off of form class
   *
   * @return FieldConfig[] array
   */
  protected function getSourceFields(ScheduledUpdateTypeInterface $updateType) {
    $source_fields = [];
    $fields = $this->FieldManager()->getFieldDefinitions('scheduled_update', $updateType->id());
    foreach ($fields as $field_id => $field) {
      if (! $field instanceof BaseFieldDefinition) {
        $source_fields[$field_id] = $field;
      }
    }
    return $source_fields;
  }


  /**
   * @return EntityFieldManagerInterface;
   */
  abstract function FieldManager();

  /**
   * @return ScheduledUpdateTypeInterface;
   */
  abstract function getEntity();

}
