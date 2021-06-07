<?php

/**
 * @file
 * Contains \Drupal\summergame\Form\SummerGameHomeCodeReportForm.
 */

namespace Drupal\summergame\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SummerGameHomeCodeReportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'summergame_home_code_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $code_id = 0) {
    $db = \Drupal::database();
    $code_id = (int) $code_id;

    $form = [
      '#attributes' => ['class' => 'form-width-exception']
    ];

    if ($code_id) {
      $game_code = $db->query("SELECT * FROM sg_game_codes WHERE code_id = $code_id AND clue LIKE '%\"homecode\"%'")->fetchObject();
    }
    if ($game_code->code_id) {
      $form['code_id'] = [
        '#type' => 'value',
        '#value' => $game_code->code_id,
      ];

      $geocode_data = json_decode($game_code->clue);

      $form['display'] = [
        '#markup' => '<h1>Report Home Code</h1>' .
        '<p>Having trouble finding the Home Code for at the following address?</p>' .
        '<p>' . $geocode_data->homecode . '</p>' .
        "<p>If you report this Home Code, we'll send a reminder to the owner to make sure it's viewable in the right location.</p>"
      ];

      $form['inline'] = [
        '#prefix' => '<div class="container-inline">',
        '#suffix' => '</div>',
      ];
      $form['inline']['submit'] = array(
        '#type' => 'submit',
        '#value' => t('Report Game Code'),
        '#prefix' => '<div class="sg-form-actions">'
      );
      $form['inline']['cancel'] = [
        '#type' => 'link',
        '#title' => $this->t('Back to Map'),
        '#url' => \Drupal\Core\Url::fromRoute('summergame.homecodes'),
      ];
    }
    else {
      \Drupal::messenger()->addError('Unable to load Home Code with ID ' . $code_id);
      return $this->redirect('summergame.homecodes');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db = \Drupal::database();
    $code_id = $form_state->getValue('code_id');

    // Grab all home code related data from code id
    $code_data = $db->query("SELECT * FROM sg_game_codes s, users_field_data u WHERE s.code_id = :code_id AND s.creator_uid = u.uid",
                            [':code_id' => $code_id])->fetchObject();

    // Update Home Code with report number
    $geocode_data = json_decode($code_data->clue);
    $geocode_data->num_reports++;
    $db->update('sg_game_codes')->fields(['clue' => json_encode($geocode_data)])->condition('code_id', $code_id)->execute();

    // Send email to Home Code owner, BCC staff notify address
    mail($code_data->mail,
      'Your Summer Game Home Code has been reported',
      "Hello there, Summer Gamer!\n" .
      "We have received a report that a player was unable to find your Home Code at the following address:\n\n" .
      str_replace('<br>', "\n", $geocode_data->homecode) . "\n\n" .
      "Please make sure that your sign is displayed in an easily viewable location from the street or sidewalk.\n" .
      "If you have any questions, please Contact Us for more information!\n\n-The Summer Game Team",
      'Bcc: ' . \Drupal::config('summergame.settings')->get('summergame_homecode_notify_email')
    );

    \Drupal::messenger()->addMessage('Home Code Reported. Thank you for helping with the Summer Game!');
    $form_state->setRedirect('summergame.homecodes');

    return;
  }
}
