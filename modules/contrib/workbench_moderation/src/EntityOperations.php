<?php

/**
 * @file
 * Contains \Drupal\workbench_moderation\EntityOperations.
 */

namespace Drupal\workbench_moderation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\workbench_moderation\Form\EntityModerationForm;

/**
 * Defines a class for reacting to entity events.
 */
class EntityOperations {

  /**
   * @var \Drupal\workbench_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new EntityOperations object.
   *
   * @param \Drupal\workbench_moderation\ModerationInformationInterface $moderation_info
   *   Moderation information service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(ModerationInformationInterface $moderation_info, EntityTypeManagerInterface $entity_type_manager, FormBuilderInterface $form_builder) {
    $this->moderationInfo = $moderation_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->formBuilder = $form_builder;
  }

  /**
   * Acts on an entity and set published status based on the moderation state.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being saved.
   */
  public function entityPresave(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface && $this->moderationInfo->isModeratableEntity($entity)) {
      // @todo write a test for this.
      if ($entity->moderation_state->entity) {
        $update_default_revision = $entity->moderation_state->entity->isDefaultRevisionState();
        $published_state = $entity->moderation_state->entity->isPublishedState();
        $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'moderation')->onPresave($entity, $update_default_revision, $published_state);
      }
    }
  }

  /**
   * Act on entities being assembled before rendering.
   *
   * This is a hook bridge.
   *
   * @see hook_entity_view()
   * @see EntityFieldManagerInterface::getExtraFields()
   */
  public function entityView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode) {

    if (!$this->moderationInfo->isModeratableEntity($entity)) {
      return;
    }
    if (!$this->moderationInfo->isLatestRevision($entity)) {
      return;
    }
    /** @var ContentEntityInterface $entity */
    if ($entity->isDefaultRevision()) {
      return;
    }

    $component = $display->getComponent('workbench_moderation_control');
    if ($component) {
      $build['workbench_moderation_control'] = $this->formBuilder->getForm(EntityModerationForm::class, $entity);
      $build['workbench_moderation_control']['#weight'] = $component['weight'];
    }
  }
}
