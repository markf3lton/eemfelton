<?php

namespace Drupal\acquia_perz\Session;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\UserSession;

/**
 * An account implementation representing a Acquia Perz user.
 */
class AcquiaPerzUserSession extends UserSession {

  /**
   * Role used to render Acquia Perz content.
   *
   * @var string
   */
  protected $renderRole;

  /**
   * Constructs a new Acquia Perz user session.
   *
   * @param string $render_role
   *   Role id.
   */
  public function __construct($render_role) {
    $this->renderRole = $render_role;
    parent::__construct(['roles' => $this->getAcquiaPerzRenderUserRoles($render_role)]);
  }

  /**
   * Obtains the user roles based on the module settings.
   *
   * @param string $render_role
   *   Role to view content.
   *
   * @return array
   *   Array of roles.
   */
  protected function getAcquiaPerzRenderUserRoles($render_role) {
    switch ($render_role) {
      case AccountInterface::ANONYMOUS_ROLE:
      case AccountInterface::AUTHENTICATED_ROLE:
        $roles = [$render_role];
        break;

      default:
        $roles = [
          AccountInterface::AUTHENTICATED_ROLE,
          $render_role,
        ];
        break;
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthenticated() {
    return $this->renderRole !== AccountInterface::ANONYMOUS_ROLE;
  }

  /**
   * {@inheritdoc}
   */
  public function isAnonymous() {
    return $this->renderRole === AccountInterface::ANONYMOUS_ROLE;
  }

}
