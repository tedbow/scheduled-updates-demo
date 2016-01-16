<?php

/**
 * @file
 * Contains \Drupal\workbench_moderation\Form\ModerationStateTransitionForm.
 */

namespace Drupal\workbench_moderation\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Class ModerationStateTransitionForm.
 *
 * @package Drupal\workbench_moderation\Form
 */
class ModerationStateTransitionForm extends EntityForm {
  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /* @var \Drupal\workbench_moderation\ModerationStateTransitionInterface $moderation_state_transition */
    $moderation_state_transition = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $moderation_state_transition->label(),
      '#description' => $this->t("Label for the Moderation state transition."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $moderation_state_transition->id(),
      '#machine_name' => [
        'exists' => '\Drupal\workbench_moderation\Entity\ModerationStateTransition::load',
      ],
      '#disabled' => !$moderation_state_transition->isNew(),
    ];

    $options = [];
    foreach (ModerationState::loadMultiple() as $moderation_state) {
      $options[$moderation_state->id()] = $moderation_state->label();
    }

    $form['container'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
    ];

    $form['container']['stateFrom'] = [
      '#type' => 'select',
      '#title' => $this->t('Transition from'),
      '#options' => $options,
      '#required' => TRUE,
      '#empty_option' => $this->t('-- Select --'),
      '#default_value' => $moderation_state_transition->getFromState(),
    ];

    $form['container']['stateTo'] = [
      '#type' => 'select',
      '#options' => $options,
      '#required' => TRUE,
      '#title' => $this->t('Transition to'),
      '#empty_option' => $this->t('-- Select --'),
      '#default_value' => $moderation_state_transition->getToState(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('stateFrom') === $form_state->getValue('stateTo')) {
      $form_state->setErrorByName('stateTo', $this->t('You cannot use the same state for both from and to.'));
    }
    parent::validateForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $moderation_state_transition = $this->entity;
    $status = $moderation_state_transition->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Moderation state transition.', [
          '%label' => $moderation_state_transition->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Moderation state transition.', [
          '%label' => $moderation_state_transition->label(),
        ]));
    }
    $form_state->setRedirectUrl($moderation_state_transition->urlInfo('collection'));
  }

}
