<?php

/**
 * Acl_Processor
 *
 * @category   Addon
 * @package    addon.acl
 * @author     Ebine Yutaka <ebine.yutaka@sabel.jp>
 * @copyright  2004-2008 Mori Reo <mori.reo@sabel.jp>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 */
class Acl_Processor extends Sabel_Bus_Processor
{
  /**
   * @var Acl_User
   */
  protected $aclUser = null;
  
  public function execute(Sabel_Bus $bus)
  {
    if (($session = $bus->get("session")) === null) {
      return $bus->getProcessorList()->remove("acl");
    }
    
    $request  = $bus->get("request");
    $response = $bus->get("response");
    
    if (!$request || !$response) {
      return;
    }
    
    $this->aclUser = $aclUser = new Acl_User($session);
    
    $_lu = $aclUser->get(Acl_User::LOGIN_URI_KEY, Acl_User::DEFAULT_SCOPE);
    if ($_lu !== null && $request->isGet() && $_lu !== $request->getUri()) {
      $aclUser->remove(Acl_User::LOGIN_URI_KEY, Acl_User::DEFAULT_SCOPE);
      $aclUser->remove(Acl_User::BACK_URI_KEY,  Acl_User::DEFAULT_SCOPE);
    }
    
    if ($controller = $bus->get("controller")) {
      $controller->setAttribute("aclUser", $aclUser);
    }
    
    if ($response->isFailure() || $response->isRedirected()) {
      return;
    }
    
    $configs = $this->getConfig();
    $destination = $bus->get("destination");
    list ($modName, $ctrlName, $actName) = $destination->toArray();
    if (!isset($configs[$modName])) return;
    
    $loginUri   = null;
    $modConfig  = $configs[$modName];
    $ctrlConfig = $modConfig->getController($ctrlName);
    
    if ($ctrlConfig !== null && ($scope = $ctrlConfig->scope()) !== null) {
      $aclUser->setScope($scope);
    } else {
      $aclUser->setScope($modConfig->scope());
    }
    
    if ($ctrlConfig === null) {
      if ($this->isAllow($modConfig)) return;
      
      $loginUri = $modConfig->authUri();
    } else {
      if ($this->isAllow($ctrlConfig)) return;
      
      if (($loginUri = $ctrlConfig->authUri()) === null) {
        $loginUri = $modConfig->authUri();
      }
    }
    
    l("ACL: access denied.", SBL_LOG_DEBUG);
    
    if ($loginUri === null || !$request->isGet()) {
      $response->getStatus()->setCode(Sabel_Response::FORBIDDEN);
    } else {
      $aclUser->enter($loginUri, "/" . $request->getUri(true));
    }
  }
  
  public function shutdown(Sabel_Bus $bus)
  {
    $this->aclUser->save();
  }
  
  protected function isAllow($config)
  {
    if ($config->isPublic()) {
      return true;
    } elseif ($this->aclUser->isAuthenticated()) {
      return $config->isAllow($this->aclUser->getRoles());
    } else {
      return false;
    }
  }
  
  protected function getConfig()
  {
    $config = new Acl_Config();
    return $config->configure();
  }
}
