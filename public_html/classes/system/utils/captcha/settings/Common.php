<?php
 namespace UmiCms\Classes\System\Utils\Captcha\Settings;class Common implements iSettings, \iUmiRegistryInjector, \iUmiConfigInjector {use \tUmiRegistryInjector;use \tUmiConfigInjector;public function __construct(\iConfiguration $vccd1066343c95877b75b79d47c36bebe, \iRegedit $va9205dcfd4a6f7c2cbe8be01566ff84a) {$this->setConfiguration($vccd1066343c95877b75b79d47c36bebe);$this->setRegistry($va9205dcfd4a6f7c2cbe8be01566ff84a);}public function getStrategyName() {if ($this->isRecaptchaEnabled()) {return 'recaptcha';}if ($this->isClassicEnabled()) {return 'captcha';}return 'null-captcha';}public function setStrategyName($vb068931cc450442b63f5b3d276ea4297) {$this->setClassicEnabled(false)    ->setRecaptchaEnabled(false);if ($vb068931cc450442b63f5b3d276ea4297 === 'recaptcha') {$this->setRecaptchaEnabled(true);}elseif ($vb068931cc450442b63f5b3d276ea4297 === 'captcha') {$this->setClassicEnabled(true);}return $this;}protected function isClassicEnabled() {return (bool) $this->getConfiguration()->get('anti-spam', 'captcha.enabled');}protected function setClassicEnabled($v327a6c4304ad5938eaf0efb6cc3e53dc) {$vccd1066343c95877b75b79d47c36bebe = $this->getConfiguration();$vccd1066343c95877b75b79d47c36bebe->set('anti-spam', 'captcha.enabled', $v327a6c4304ad5938eaf0efb6cc3e53dc);$vccd1066343c95877b75b79d47c36bebe->save();return $this;}protected function isRecaptchaEnabled() {return (bool) $this->getRegistry()->get('//settings/enable-recaptcha');}protected function setRecaptchaEnabled($v327a6c4304ad5938eaf0efb6cc3e53dc) {$this->getRegistry()->set('//settings/enable-recaptcha', $v327a6c4304ad5938eaf0efb6cc3e53dc);return $this;}public function shouldRemember() {return (bool) $this->getRegistry()->get('//settings/captcha-remember');}public function setShouldRemember($v327a6c4304ad5938eaf0efb6cc3e53dc) {$this->getRegistry()->set('//settings/captcha-remember', $v327a6c4304ad5938eaf0efb6cc3e53dc);return $this;}public function getDrawerName() {return (string) $this->getConfiguration()->get('anti-spam', 'captcha.drawer');}public function setDrawerName($vb068931cc450442b63f5b3d276ea4297) {$vccd1066343c95877b75b79d47c36bebe = $this->getConfiguration();$vccd1066343c95877b75b79d47c36bebe->set('anti-spam', 'captcha.drawer', $vb068931cc450442b63f5b3d276ea4297);$vccd1066343c95877b75b79d47c36bebe->save();return $this;}public function getSitekey() {return (string) $this->getRegistry()->get('//settings/recaptcha-sitekey');}public function setSitekey($v4d472c8c75568efd59744f7399f271f7) {$this->getRegistry()->set('//settings/recaptcha-sitekey', $v4d472c8c75568efd59744f7399f271f7);return $this;}public function getSecret() {return (string) $this->getRegistry()->get('//settings/recaptcha-secret');}public function setSecret($v5ebe2294ecd0e0f08eab7690d2a6ee69) {$this->getRegistry()->set('//settings/recaptcha-secret', $v5ebe2294ecd0e0f08eab7690d2a6ee69);return $this;}}