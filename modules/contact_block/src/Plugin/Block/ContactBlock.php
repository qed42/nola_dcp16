<?php

/**
 * @file
 * Contains Drupal\contact_block\Plugin\Block\ContactBlock.
 */

namespace Drupal\contact_block\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Config\ConfigFactory;

/**
 * Provides a 'ContactBlock' block.
 *
 * @Block(
 *  id = "contact_block",
 *  admin_label = @Translation("Contact block"),
 * )
 */
class ContactBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The EntityManager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The ConfigFactory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The EntityFormBuilder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * The Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The contact form configuration entity.
   *
   * @var \Drupal\contact\Entity\ContactForm
   */
  protected $contactForm;

  /**
   * Constructor for ContactBlock block class.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param EntityManager $entity_manager
   *   The entity manager.
   * @param ConfigFactory $config_factory
   *   The config factory.
   * @param EntityFormBuilder $entity_form_builder
   *   The entity form builder.
   * @param Renderer $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityManager $entity_manager, ConfigFactory $config_factory, EntityFormBuilder $entity_form_builder, Renderer $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->configFactory = $config_factory;
    $this->entityFormBuilder = $entity_form_builder;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager'),
      $container->get('config.factory'),
      $container->get('entity.form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    $contact_form = $this->getContactForm();
    $contact_message = $this->createContactMessage();

    // Deny access when the configured contact form has been deleted.
    if (empty($contact_form)) {
      return AccessResult::forbidden();
    }

    if ($contact_message->isPersonal()) {
      /** @var \Drupal\user\Entity\User $user */
      $user = \Drupal::routeMatch()->getParameter('user');

      // Deny access to the contact form if we are not on a user related page
      // or we have no access to that page.
      if (empty($user)) {
        return AccessResult::forbidden();
      }

      return AccessResult::allowedIfHasPermission($account, 'access user contact forms');
    }

    // Access to other contact forms is equal to the permission of the
    // entity.contact_form.canonical route.
    return $contact_form->access('view', $account, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array(
      'label' => t('Contact block'),
      'contact_form' => 'personal',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $options = $this->entityManager
      ->getStorage('contact_form')
      ->loadMultiple();
    foreach ($options as $key => $option) {
      $options[$key] = $option->label();
    }

    $form['contact_form'] = array(
      '#type' => 'select',
      '#title' => $this->t('Contact form'),
      '#options' => $options,
      '#default_value' => $this->configuration['contact_form'],
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['contact_form'] = $form_state->getValue('contact_form');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = array();

    /** @var \Drupal\contact\Entity\ContactForm $contact_form */
    $contact_form = $this->getContactForm();
    if ($contact_form) {
      $contact_message = $this->createContactMessage();

      // The personal contact form has a fixed recipient: the user who's
      // contact page we visit. We use the 'user' property from the URL
      // to determine this user. For example: user/{user}.
      if ($contact_message->isPersonal()) {
        $user = \Drupal::routeMatch()->getParameter('user');
        $contact_message->set('recipient', $user);
      }

      $form = $this->entityFormBuilder->getForm($contact_message);
      $form['#cache']['contexts'][] = 'user.permissions';
      $this->renderer->addCacheableDependency($form, $contact_form);
    }

    return $form;
  }

  /**
   * Loads the contact form entity.
   *
   * @return \Drupal\contact\Entity\ContactForm|null
   *   The contact form configuration entity. NULL if the entity does not exist.
   */
  protected function getContactForm() {
    if (!isset($this->contactForm)) {
      if (isset($this->configuration['contact_form'])) {
        $this->contactForm = $this->entityManager
          ->getStorage('contact_form')
          ->load($this->configuration['contact_form']);
      }
    }
    return $this->contactForm;
  }

  /**
   * Creates the contact message entity without saving it.
   *
   * @return \Drupal\contact\Entity\Message|null
   *   The contact message entity. NULL if the entity does not exist.
   */
  protected function createContactMessage() {
    $contact_message = NULL;

    $contact_form = $this->getContactForm();
    if ($contact_form) {
      $contact_message = $this->entityManager
        ->getStorage('contact_message')
        ->create(['contact_form' => $contact_form->id()]);
    }
    return $contact_message;
  }
}
