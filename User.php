<?php

/**
 * Acl_User
 *
 * @category   Addon
 * @package    addon.acl
 * @author     Mori Reo <mori.reo@sabel.jp>
 * @author     Ebine Yutaka <ebine.yutaka@sabel.jp>
 * @copyright  2004-2008 Mori Reo <mori.reo@sabel.jp>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class Acl_User extends Sabel_Object
{
  const DEFAULT_SCOPE = "__default";
  
  const SESSION_KEY   = "sbl_acl_user";
  const LOGIN_URI_KEY = "__login_uri";
  const BACK_URI_KEY  = "__back_uri";
  
  /**
   * @var Sabel_Session_Abstract
   */
  protected $session = null;
  
  /**
   * @var string
   */
  protected $scope = self::DEFAULT_SCOPE;
  
  /**
   * @var array
   */
  protected $values = array();
  
  public function __construct(Sabel_Session_Abstract $session)
  {
    $this->session = $session;
    
    if (($values = $session->read(self::SESSION_KEY)) === null) {
      $this->values = array("roles" => array());
    } else {
      $this->values = $values;
    }
  }
  
  public function save()
  {
    $this->session->write(self::SESSION_KEY, $this->values);
  }
  
  public function setScope($scope)
  {
    if ($scope === null) {
      $this->scope = self::DEFAULT_SCOPE;
    } elseif (is_string($scope)) {
      $this->scope = $scope;
    } else {
      $message = __METHOD__ . "() argument must be a string.";
      throw new Sabel_Exception_InvalidArgument($message);
    }
  }
  
  public function getScope()
  {
    return $this->scope;
  }
  
  public function set($key, $value, $scope = null)
  {
    if ($scope === null) {
      $scope = $this->scope;
    }
    
    $vs =& $this->values;
    
    if (!isset($vs[$scope])) {
      $vs[$scope] = array();
    }
    
    $vs[$scope][$key] = $value;
  }
  
  public function __set($key, $value)
  {
    $this->set($key, $value);
  }
  
  public function get($key, $scope = null)
  {
    if ($scope === null) {
      $scope = $this->scope;
    }
    
    $vs = $this->values;
    
    if (isset($vs[$scope][$key])) {
      return $vs[$scope][$key];
    } else {
      return null;
    }
  }
  
  public function __get($key)
  {
    return $this->get($key);
  }
  
  public function remove($key, $scope = null)
  {
    if ($scope === null) {
      $scope = $this->scope;
    }
    
    $value = $this->get($key);
    unset($this->values[$this->scope][$key]);
    
    return $value;
  }
  
  public function clearData($scope = null)
  {
    if ($scope === null) {
      $scope = $this->scope;
    }
    
    unset($this->values[$scope]);
  }
  
  public function isAuthenticated()
  {
    return !empty($this->values["roles"]);
  }
  
  public function authenticate($role, $regenerateId = true)
  {
    l("ACL: authenticate.", SBL_LOG_DEBUG);
    
    $this->addRole($role);
    
    if ($regenerateId) {
      $this->session->regenerateId();
    }
  }
  
  public function deAuthenticate($deleteCookie = true)
  {
    l("ACL: deAuthenticate.", SBL_LOG_DEBUG);
    
    $this->values = array("roles" => array());
    
    if ($deleteCookie) {
      Sabel_Cookie_Factory::create()->delete($this->session->getName());
    }
  }
  
  public function enter($redirectTo, $backTo = null)
  {
    if ($response = Sabel_Context::getResponse()) {
      $to = ltrim($response->getRedirector()->to($redirectTo), "/");
      $this->set(self::LOGIN_URI_KEY, $to, self::DEFAULT_SCOPE);
      
      if ($backTo !== null) {
        if ($backTo{0} !== "/") {
          $backTo = "/" . $backTo;
        }
        
        if (defined("URI_PREFIX") && strpos($backTo, URI_PREFIX) === 0) {
          $backTo = substr($backTo, strlen(URI_PREFIX));
        }
        
        $this->set(self::BACK_URI_KEY, $backTo, self::DEFAULT_SCOPE);
      }
    } else {
      $message = __METHOD__ . "() response is null.";
      throw new Sabel_Exception_Runtime($message);
    }
  }
  
  public function login($redirectTo)
  {
    $roles = func_get_args();
    array_shift($roles);
    $this->authenticate($roles[0]);
    
    if (($c = count($roles)) > 1) {
      for ($i = 1; $i < $c; $i++) {
        $this->addRole($roles[$i]);
      }
    }
    
    if ($response = Sabel_Context::getResponse()) {
      if (($backUri = $this->get(self::BACK_URI_KEY, self::DEFAULT_SCOPE)) === null) {
        $response->getRedirector()->to($redirectTo);
      } else {
        $params = array();
        $parsed = parse_url("http://localhost/" . ltrim($backUri, "/"));
        
        if (isset($parsed["query"]) && !is_empty($parsed["query"])) {
          parse_str($parsed["query"], $params);
          unset($params[$this->session->getName()]);
        }
        
        $response->getRedirector()->uri($parsed["path"], $params);
        
        l("ACL: back to the page before authentication.", SBL_LOG_DEBUG);
      }
    }
    
    $this->remove(self::LOGIN_URI_KEY, self::DEFAULT_SCOPE);
    $this->remove(self::BACK_URI_KEY,  self::DEFAULT_SCOPE);
  }
  
  public function logout($role, $clearScopeData = true)
  {
    $this->removeRole($role);
    
    if ($clearScopeData) {
      $this->clearData();
    }
  }
  
  public function addRole($role)
  {
    if (!$this->hasRole($role)) {
      $this->values["roles"][] = $role;
    }
  }
  
  public function getRoles()
  {
    return $this->values["roles"];
  }
  
  public function hasRole($role)
  {
    return in_array($role, $this->values["roles"], true);
  }
  
  public function removeRole($role)
  {
    if (($idx = array_search($role, $this->values["roles"], true)) !== false) {
      unset($this->values["roles"][$idx]);
    }
  }
}
