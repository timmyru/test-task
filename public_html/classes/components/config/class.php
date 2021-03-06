<?php

	use UmiCms\Service;

	/**
	 * Класс базовых настроек системы.
	 * Помимо настроек, отвечает за управление:
	 * 1) Модулями;
	 * 2) Доменами;
	 * 3) Языками;
	 * @link http://help.docs.umi-cms.ru/rabota_s_modulyami/modul_konfiguraciya/
	 */
	class config extends def_module {

		/**
		 * Конструктор
		 * @throws coreException
		 */
		public function __construct() {
			parent::__construct();

			if (Service::Request()->isAdmin()) {
				$this->initTabs()
					->includeAdminClasses();
			}

			$this->includeCommonClasses();
		}

		/**
		 * Создает вкладки административной панели модуля
		 * @return $this
		 */
		public function initTabs() {
			$commonTabs = $this->getCommonTabs();

			if ($commonTabs instanceof iAdminModuleTabs) {
				$commonTabs->add('main');
				$commonTabs->add('solutions');
				$commonTabs->add('modules');
				$commonTabs->add('extensions');
				$commonTabs->add('langs');
				$commonTabs->add('domains', ['domain_mirrows']);
				$commonTabs->add('mails');
				$commonTabs->add('cache');
				$commonTabs->add('security');
				$commonTabs->add('phpInfo');
				$commonTabs->add('watermark');
				$commonTabs->add('captcha');
			}

			return $this;
		}

		/**
		 * Подключает классы функционала административной панели
		 * @return $this
		 */
		public function includeAdminClasses() {
			$this->__loadLib('admin.php');
			$this->__implement('ConfigAdmin');

			$this->loadAdminExtension();

			$this->__loadLib('customAdmin.php');
			$this->__implement('ConfigCustomAdmin', true);

			$this->__loadLib('tests.php');
			$this->__implement('ConfigTest');

			return $this;
		}

		/**
		 * Подключает общие классы функционала
		 * @return $this
		 */
		public function includeCommonClasses() {
			$this->loadSiteExtension();

			$this->__loadLib('handlers.php');
			$this->__implement('ConfigHandlers');

			$this->__loadLib('customMacros.php');
			$this->__implement('ConfigCustomMacros', true);

			$this->loadCommonExtension();
			$this->loadTemplateCustoms();

			return $this;
		}

		/**
		 * Возвращает меню установленных модулей
		 * @return array
		 */
		public function menu() {
			$blockArr = [];
			$regedit = Service::Registry();
			$modules = $this->getSortedModulesList();

			$result = [];

			foreach ($modules as $moduleName => $moduleInfo) {
				$moduleConfig = $regedit->get("//modules/{$moduleName}/config");
				$currentModule = cmsController::getInstance()->getCurrentModule();
				$currentMethod = cmsController::getInstance()->getCurrentMethod();

				$lineArr = [];
				$lineArr['attribute:name'] = $moduleInfo['name'];
				$lineArr['attribute:label'] = $moduleInfo['label'];
				$lineArr['attribute:type'] = $moduleInfo['type'];

				if ($currentModule == $moduleName && !($currentMethod == 'mainpage')) {
					$lineArr['attribute:active'] = 'active';
				}

				if ($moduleConfig && system_is_allowed($currentModule, 'config')) {
					$lineArr['attribute:config'] = 'config';
				}

				$result[] = $lineArr;
			}

			$blockArr['items'] = ['nodes:item' => $result];
			return $blockArr;
		}

		/**
		 * Запускает установку модуля и
		 * перенаправляет на список установленных
		 * модулей
		 * @throws publicAdminException
		 * @throws coreException
		 * @throws errorPanicException
		 * @throws privateException
		 * @throws ErrorException
		 */
		public function add_module_do() {
			if (isDemoMode()) {
				$this->errorNewMessage(getLabel('js-label-stop-in-demo'));
				return;
			}

			$cmsController = cmsController::getInstance();
			$modulePath = getRequest('module_path');

			if (!preg_match("/.\.php$/", $modulePath)) {
				$modulePath .= '/install.php';
			}

			$cmsController->installModule($modulePath);

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . '/admin/config/modules/');
		}

		/**
		 * Запускает удаление модуля и перенаправляет на список установленны модулей
		 * @throws publicAdminException
		 * @throws coreException
		 * @throws errorPanicException
		 * @throws privateException
		 * @throws ErrorException
		 */
		public function del_module() {
			if (isDemoMode()) {
				$this->errorNewMessage(getLabel('js-label-stop-in-demo'));
				return;
			}

			$restrictedModules = ['config', 'content', 'users', 'data'];
			$target = getRequest('param0');

			if (in_array($target, $restrictedModules)) {
				throw new publicAdminException(getLabel("error-can-not-delete-{$target}-module"));
			}

			$module = cmsController::getInstance()
				->getModule($target);

			if ($module instanceof def_module) {
				$module->uninstall();
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . '/admin/config/modules/');
		}

		/**
		 * Запускает удаление расширения и перенаправляет на список установленных расширений.
		 * Требует наличия скаченных пакетов обновления (/sys-temp/updates/).
		 * @param string|null $name название расширения
		 * @throws coreException
		 * @throws errorPanicException
		 * @throws privateException
		 * @throws ErrorException
		 * @throws publicException
		 */
		public function deleteExtension($name = null) {
			if (isDemoMode()) {
				$this->errorNewMessage(getLabel('js-label-stop-in-demo'));
				return;
			}

			$name = $name ?: (string) getRequest('param0');
			$registry = Service::ExtensionRegistry();

			if ($registry->contains($name)) {
				$dumpPath = $this->getDumpPath($name);

				$importer = new xmlImporter('system');
				$importer->loadXmlFile($dumpPath);
				$importer->demolish();

				$registry->delete($name);
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . '/admin/config/extensions/');
		}

		/**
		 * Запускает удаление решения и перенаправляет на список установленных решений.
		 * Требует наличия скаченных пакетов обновления (/sys-temp/updates/).
		 * @param string|null $name название решения
		 * @param int|null $domainId идентификатор, куда устанавливает решение
		 * @throws coreException
		 * @throws errorPanicException
		 * @throws privateException
		 * @throws ErrorException
		 * @throws publicException
		 */
		public function deleteSolution($name = null, $domainId = null) {
			if (isDemoMode()) {
				$this->errorNewMessage(getLabel('js-label-stop-in-demo'));
				return;
			}

			$name = $name ?: (string) getRequest('param0');
			$domainId = $domainId ?: (string) getRequest('param1');
			$registry = Service::SolutionRegistry();

			if ($registry->isAppendedToDomain($name, $domainId)) {
				$dumpPath = $this->getDumpPath($name);

				$source = Service::UmiDumpSolutionPostfixBuilder()
					->run($name, $domainId);
				$importer = new xmlImporter($source);
				$importer->loadXmlFile($dumpPath);
				$importer->demolish();

				$registry->deleteFromDomain($domainId);
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . '/admin/config/solutions/');
		}

		/**
		 * Возвращает настройки статического кеша
		 * @return array
		 */
		public function getStaticCacheSettings() {
			$enabled = Service::StaticCache()->isEnabled();

			return $enabled
				? $settings = [
					'enabled' => $enabled,
					'expire' => mainConfiguration::getInstance()
						->get('cache', 'static.mode')
				]
				: [
					'enabled' => false,
					'expire' => false
				];
		}

		/**
		 * Устанавливает настройки статического кеша и возвращает
		 * результат операции
		 * @param array $settings настройки статического кеша
		 * @return bool
		 */
		public function setStaticCacheSettings($settings) {
			if (!is_array($settings)) {
				return false;
			}

			$config = mainConfiguration::getInstance();
			$config->set('cache', 'static.enabled', getArrayKey($settings, 'enabled'));
			$config->set('cache', 'static.mode', getArrayKey($settings, 'expire'));
			$config->save();

			return true;
		}

		/**
		 * Возвращает настройки кеширования протоколов системы
		 * @return array
		 */
		public function getStreamsCacheSettings() {
			$config = mainConfiguration::getInstance();
			$enabled = $config->get('cache', 'streams.cache-enabled');

			if ($enabled) {
				return [
					'cache-enabled' => $enabled,
					'cache-lifetime' => $config->get('cache', 'streams.cache-lifetime'),
				];
			}

			return [
				'cache-enabled' => false,
				'cache-lifetime' => 0,
			];
		}

		/**
		 * Устанавливает настройки кеширования протоколов системы и возвращает
		 * результат операции
		 * @param array $settings настройки кеширования протоколов системы
		 * @return bool
		 */
		public function setStreamsCacheSettings($settings) {
			if (!is_array($settings)) {
				return false;
			}

			$config = mainConfiguration::getInstance();
			$config->set('cache', 'streams.cache-enabled', getArrayKey($settings, 'cache-enabled'));
			$config->set('cache', 'streams.cache-lifetime', getArrayKey($settings, 'cache-lifetime'));
			$config->save();

			return true;
		}

		/**
		 * Возвращает настройки браузерного кеша
		 * @return array
		 */
		public function getBrowserCacheSettings() {
			return [
				'current-engine' => Service::BrowserCache()
					->getEngineName()
			];
		}

		/**
		 * Сохраняет настройки браузерного кеша
		 * @param array $settings
		 * @return bool
		 */
		public function setBrowserCacheSettings(array $settings) {
			$engineName = isset($settings['current-engine']) ? $settings['current-engine'] : null;

			Service::BrowserCache()
				->setEngine($engineName);

			return true;
		}

		/**
		 * Удаляет зеркало домена
		 * @throws coreException
		 */
		public function domain_mirrow_del() {
			$domain_id = (int) getRequest('param0');
			$domain_mirror_id = (int) getRequest('param1');

			if (!isDemoMode()) {
				$domain = Service::DomainCollection()->getDomain($domain_id);
				$domain->delMirror($domain_mirror_id);
				$domain->commit();
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . "/admin/config/domain_mirrows/{$domain_id}/");
		}

		/**
		 * Удаляет домен
		 * @throws coreException
		 * @throws publicAdminException
		 */
		public function domain_del() {
			$domain_id = (int) getRequest('param0');
			$domainCollection = Service::DomainCollection();

			if ($domain_id == $domainCollection->getDefaultDomain()->getId()) {
				throw new publicAdminException(getLabel('error-can-not-delete-default-domain'));
			}

			if (!isDemoMode()) {
				$domainCollection->delDomain($domain_id);
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($this->pre_lang . '/admin/config/domains/');
		}

		/**
		 * Удаляет язык
		 * @throws coreException
		 * @throws publicAdminException
		 */
		public function lang_del() {
			$langId = (int) getRequest('param0');
			$langs = Service::LanguageCollection();

			if (umiCount($langs->getList()) == 1) {
				throw new publicAdminException(getLabel('error-minimum-one-lang-required'));
			}

			if ($langs->getDefaultLang()->getId() == $langId) {
				throw new publicAdminException(getLabel('error-try-delete-default-language'));
			}

			$currentLangId = Service::LanguageDetector()->detectId();

			$url = '/admin/config/langs/';
			if ($currentLangId != $langId) {
				$url = $this->pre_lang . $url;
			}

			if (!isDemoMode()) {
				$langs->delLang($langId);
			}

			/** @var config|ConfigAdmin $this */
			$this->chooseRedirect($url);
		}

		/**
		 * Создает сообщение и отправляет уведомление супервайзерам.
		 * @param string $title заголовок сообщения
		 * @param string $content контент сообщения
		 * @throws coreException
		 * @throws privateException
		 * @throws selectorException
		 */
		public function dispatchSystemEvent($title, $content) {
			$recipients = $this->getSystemEventRecipients();

			if (umiCount($recipients)) {
				$messages = umiMessages::getInstance();
				$message = $messages->create();
				$message->setTitle($title);
				$message->setContent($content);
				$message->setType('sys-log');
				$message->commit();
				$message->send($recipients);
			}
		}

		/**
		 * Возвращает список временных зон
		 * @return array
		 */
		public function getTimeZones() {
			return [
				'' => '',
				'Pacific/Apia' => '(-11:00) Pacific/Apia',
				'Pacific/Midway' => '(-11:00) Pacific/Midway',
				'Pacific/Niue' => '(-11:00) Pacific/Niue',
				'Pacific/Pago_Pago' => '(-11:00) Pacific/Pago_Pago',
				'Pacific/Samoa' => '(-11:00) Pacific/Samoa',
				'Pacific/Marquesas' => '(-10:0-30) Pacific/Marquesas',
				'Pacific/Fakaofo' => '(-10:00) Pacific/Fakaofo',
				'Pacific/Honolulu' => '(-10:00) Pacific/Honolulu',
				'Pacific/Johnston' => '(-10:00) Pacific/Johnston',
				'Pacific/Rarotonga' => '(-10:00) Pacific/Rarotonga',
				'Pacific/Tahiti' => '(-10:00) Pacific/Tahiti',
				'America/Adak' => '(-10:00) America/Adak',
				'America/Atka' => '(-10:00) America/Atka',
				'America/Anchorage' => '(-09:00) America/Anchorage',
				'America/Juneau' => '(-09:00) America/Juneau',
				'America/Nome' => '(-09:00) America/Nome',
				'America/Yakutat' => '(-09:00) America/Yakutat',
				'Pacific/Gambier' => '(-09:00) Pacific/Gambier',
				'America/Dawson' => '(-08:00) America/Dawson',
				'America/Ensenada' => '(-08:00) America/Ensenada',
				'America/Los_Angeles' => '(-08:00) America/Los_Angeles',
				'America/Tijuana' => '(-08:00) America/Tijuana',
				'America/Vancouver' => '(-08:00) America/Vancouver',
				'America/Whitehorse' => '(-08:00) America/Whitehorse',
				'Pacific/Pitcairn' => '(-08:00) Pacific/Pitcairn',
				'America/Boise' => '(-07:00) America/Boise',
				'America/Cambridge_Bay' => '(-07:00) America/Cambridge_Bay',
				'America/Chihuahua' => '(-07:00) America/Chihuahua',
				'America/Dawson_Creek' => '(-07:00) America/Dawson_Creek',
				'America/Denver' => '(-07:00) America/Denver',
				'America/Edmonton' => '(-07:00) America/Edmonton',
				'America/Hermosillo' => '(-07:00) America/Hermosillo',
				'America/Inuvik' => '(-07:00) America/Inuvik',
				'America/Mazatlan' => '(-07:00) America/Mazatlan',
				'America/Phoenix' => '(-07:00) America/Phoenix',
				'America/Shiprock' => '(-07:00) America/Shiprock',
				'America/Yellowknife' => '(-07:00) America/Yellowknife',
				'America/Belize' => '(-06:00) America/Belize',
				'America/Cancun' => '(-06:00) America/Cancun',
				'America/Chicago' => '(-06:00) America/Chicago',
				'America/Costa_Rica' => '(-06:00) America/Costa_Rica',
				'America/El_Salvador' => '(-06:00) America/El_Salvador',
				'America/Guatemala' => '(-06:00) America/Guatemala',
				'America/Managua' => '(-06:00) America/Managua',
				'America/Menominee' => '(-06:00) America/Menominee',
				'America/Merida' => '(-06:00) America/Merida',
				'America/Mexico_City' => '(-06:00) America/Mexico_City',
				'America/Monterrey' => '(-06:00) America/Monterrey',
				'America/North_Dakota/Center' => '(-06:00) America/North_Dakota/Center',
				'America/Rainy_River' => '(-06:00) America/Rainy_River',
				'America/Rankin_Inlet' => '(-06:00) America/Rankin_Inlet',
				'America/Regina' => '(-06:00) America/Regina',
				'America/Swift_Current' => '(-06:00) America/Swift_Current',
				'America/Tegucigalpa' => '(-06:00) America/Tegucigalpa',
				'America/Winnipeg' => '(-06:00) America/Winnipeg',
				'Pacific/Easter' => '(-06:00) Pacific/Easter',
				'Pacific/Galapagos' => '(-06:00) Pacific/Galapagos',
				'America/Bogota' => '(-05:00) America/Bogota',
				'America/Cayman' => '(-05:00) America/Cayman',
				'America/Detroit' => '(-05:00) America/Detroit',
				'America/Eirunepe' => '(-05:00) America/Eirunepe',
				'America/Fort_Wayne' => '(-05:00) America/Fort_Wayne',
				'America/Grand_Turk' => '(-05:00) America/Grand_Turk',
				'America/Guayaquil' => '(-05:00) America/Guayaquil',
				'America/Havana' => '(-05:00) America/Havana',
				'America/Indiana/Indianapolis' => '(-05:00) America/Indiana/Indianapolis',
				'America/Indiana/Knox' => '(-05:00) America/Indiana/Knox',
				'America/Indiana/Marengo' => '(-05:00) America/Indiana/Marengo',
				'America/Indiana/Vevay' => '(-05:00) America/Indiana/Vevay',
				'America/Indianapolis' => '(-05:00) America/Indianapolis',
				'America/Iqaluit' => '(-05:00) America/Iqaluit',
				'America/Jamaica' => '(-05:00) America/Jamaica',
				'America/Kentucky/Louisville' => '(-05:00) America/Kentucky/Louisville',
				'America/Kentucky/Monticello' => '(-05:00) America/Kentucky/Monticello',
				'America/Knox_IN' => '(-05:00) America/Knox_IN',
				'America/Lima' => '(-05:00) America/Lima',
				'America/Louisville' => '(-05:00) America/Louisville',
				'America/Montreal' => '(-05:00) America/Montreal',
				'America/Nassau' => '(-05:00) America/Nassau',
				'America/New_York' => '(-05:00) America/New_York',
				'America/Nipigon' => '(-05:00) America/Nipigon',
				'America/Panama' => '(-05:00) America/Panama',
				'America/Pangnirtung' => '(-05:00) America/Pangnirtung',
				'America/Port-au-Prince' => '(-05:00) America/Port-au-Prince',
				'America/Porto_Acre' => '(-05:00) America/Porto_Acre',
				'America/Rio_Branco' => '(-05:00) America/Rio_Branco',
				'America/Thunder_Bay' => '(-05:00) America/Thunder_Bay',
				'America/Anguilla' => '(-04:00) America/Anguilla',
				'America/Antigua' => '(-04:00) America/Antigua',
				'America/Aruba' => '(-04:00) America/Aruba',
				'America/Asuncion' => '(-04:00) America/Asuncion',
				'America/Barbados' => '(-04:00) America/Barbados',
				'America/Boa_Vista' => '(-04:00) America/Boa_Vista',
				'America/Caracas' => '(-04:00) America/Caracas',
				'America/Cuiaba' => '(-04:00) America/Cuiaba',
				'America/Curacao' => '(-04:00) America/Curacao',
				'America/Dominica' => '(-04:00) America/Dominica',
				'America/Glace_Bay' => '(-04:00) America/Glace_Bay',
				'America/Goose_Bay' => '(-04:00) America/Goose_Bay',
				'America/Grenada' => '(-04:00) America/Grenada',
				'America/Guadeloupe' => '(-04:00) America/Guadeloupe',
				'America/Guyana' => '(-04:00) America/Guyana',
				'America/Halifax' => '(-04:00) America/Halifax',
				'America/La_Paz' => '(-04:00) America/La_Paz',
				'America/Manaus' => '(-04:00) America/Manaus',
				'America/Martinique' => '(-04:00) America/Martinique',
				'America/Montserrat' => '(-04:00) America/Montserrat',
				'America/Port_of_Spain' => '(-04:00) America/Port_of_Spain',
				'America/Porto_Velho' => '(-04:00) America/Porto_Velho',
				'America/Puerto_Rico' => '(-04:00) America/Puerto_Rico',
				'America/Santiago' => '(-04:00) America/Santiago',
				'America/Santo_Domingo' => '(-04:00) America/Santo_Domingo',
				'America/St_Johns' => '(-04:0-30) America/St_Johns',
				'America/St_Kitts' => '(-04:00) America/St_Kitts',
				'America/St_Lucia' => '(-04:00) America/St_Lucia',
				'America/St_Thomas' => '(-04:00) America/St_Thomas',
				'America/St_Vincent' => '(-04:00) America/St_Vincent',
				'America/Thule' => '(-04:00) America/Thule',
				'America/Tortola' => '(-04:00) America/Tortola',
				'America/Virgin' => '(-04:00) America/Virgin',
				'Antarctica/Palmer' => '(-04:00) Antarctica/Palmer',
				'Atlantic/Bermuda' => '(-04:00) Atlantic/Bermuda',
				'Atlantic/Stanley' => '(-04:00) Atlantic/Stanley',
				'America/Araguaina' => '(-03:00) America/Araguaina',
				'America/Belem' => '(-03:00) America/Belem',
				'America/Buenos_Aires' => '(-03:00) America/Buenos_Aires',
				'America/Catamarca' => '(-03:00) America/Catamarca',
				'America/Cayenne' => '(-03:00) America/Cayenne',
				'America/Cordoba' => '(-03:00) America/Cordoba',
				'America/Godthab' => '(-03:00) America/Godthab',
				'America/Fortaleza' => '(-03:00) America/Fortaleza',
				'America/Jujuy' => '(-03:00) America/Jujuy',
				'America/Maceio' => '(-03:00) America/Maceio',
				'America/Mendoza' => '(-03:00) America/Mendoza',
				'America/Miquelon' => '(-03:00) America/Miquelon',
				'America/Montevideo' => '(-03:00) America/Montevideo',
				'America/Paramaribo' => '(-03:00) America/Paramaribo',
				'America/Recife' => '(-03:00) America/Recife',
				'America/Rosario' => '(-03:00) America/Rosario',
				'America/Sao_Paulo' => '(-03:00) America/Sao_Paulo',
				'America/Noronha' => '(-02:00) America/Noronha',
				'Atlantic/South_Georgia' => '(-02:00) Atlantic/South_Georgia',
				'America/Scoresbysund' => '(-01:00) America/Scoresbysund',
				'Atlantic/Cape_Verde' => '(-01:00) Atlantic/Cape_Verde',
				'Atlantic/Canary' => '(-00:00) Atlantic/Canary',
				'Atlantic/Faeroe' => '(-00:00) Atlantic/Faeroe',
				'Atlantic/Madeira' => '(-00:00) Atlantic/Madeira',
				'Atlantic/Reykjavik' => '(-00:00) Atlantic/Reykjavik',
				'Atlantic/St_Helena' => '(-00:00) Atlantic/St_Helena',
				'Europe/Belfast' => '(-00:00) Europe/Belfast',
				'Europe/Dublin' => '(-00:00) Europe/Dublin',
				'Europe/Guernsey' => '(-00:00) Europe/Guernsey',
				'Europe/Isle_of_Man' => '(-00:00) Europe/Isle_of_Man',
				'Europe/Jersey' => '(-00:00) Europe/Jersey',
				'Europe/Lisbon' => '(-00:00) Europe/Lisbon',
				'Europe/London' => '(-00:00) Europe/London',
				'Europe/Mariehamn' => '(-00:00) Europe/Mariehamn',
				'Europe/Volgograd' => '(-00:00) Europe/Volgograd',
				'Africa/Abidjan' => '(-00:00) Africa/Abidjan',
				'Africa/Accra' => '(-00:00) Africa/Accra',
				'Africa/Bamako' => '(-00:00) Africa/Bamako',
				'Africa/Banjul' => '(-00:00) Africa/Banjul',
				'Africa/Bissau' => '(-00:00) Africa/Bissau',
				'Africa/Casablanca' => '(-00:00) Africa/Casablanca',
				'Africa/Conakry' => '(-00:00) Africa/Conakry',
				'Africa/Dakar' => '(-00:00) Africa/Dakar',
				'Africa/El_Aaiun' => '(-00:00) Africa/El_Aaiun',
				'Africa/Freetown' => '(-00:00) Africa/Freetown',
				'Africa/Lome' => '(-00:00) Africa/Lome',
				'Africa/Monrovia' => '(-00:00) Africa/Monrovia',
				'Africa/Nouakchott' => '(-00:00) Africa/Nouakchott',
				'Africa/Ouagadougou' => '(-00:00) Africa/Ouagadougou',
				'Africa/Sao_Tome' => '(-00:00) Africa/Sao_Tome',
				'Africa/Timbuktu' => '(-00:00) Africa/Timbuktu',
				'America/Argentina/Buenos_Aires' => '(-00:00) America/Argentina/Buenos_Aires',
				'America/Argentina/Catamarca' => '(-00:00) America/Argentina/Catamarca',
				'America/Argentina/ComodRivadavia' => '(-00:00) America/Argentina/ComodRivadavia',
				'America/Argentina/Cordoba' => '(-00:00) America/Argentina/Cordoba',
				'America/Argentina/Jujuy' => '(-00:00) America/Argentina/Jujuy',
				'America/Argentina/La_Rioja' => '(-00:00) America/Argentina/La_Rioja',
				'America/Argentina/Mendoza' => '(-00:00) America/Argentina/Mendoza',
				'America/Argentina/Rio_Gallegos' => '(-00:00) America/Argentina/Rio_Gallegos',
				'America/Argentina/San_Juan' => '(-00:00) America/Argentina/San_Juan',
				'America/Argentina/Tucuman' => '(-00:00) America/Argentina/Tucuman',
				'America/Argentina/Ushuaia' => '(-00:00) America/Argentina/Ushuaia',
				'America/Atikokan' => '(-00:00) America/Atikokan',
				'America/Bahia' => '(-00:00) America/Bahia',
				'America/Blanc-Sablon' => '(-00:00) America/Blanc-Sablon',
				'America/Campo_Grande' => '(-00:00) America/Campo_Grande',
				'America/Coral_Harbour' => '(-00:00) America/Coral_Harbour',
				'America/Danmarkshavn' => '(-00:00) America/Danmarkshavn',
				'America/Indiana/Petersburg' => '(-00:00) America/Indiana/Petersburg',
				'America/Indiana/Vincennes' => '(-00:00) America/Indiana/Vincennes',
				'America/Moncton' => '(-00:00) America/Moncton',
				'America/North_Dakota/New_Salem' => '(-00:00) America/North_Dakota/New_Salem',
				'America/Toronto' => '(-00:00) America/Toronto',
				'Antarctica/Rothera' => '(-00:00) Antarctica/Rothera',
				'Antarctica/VostokArctic/Longyearbyen' => '(-00:00) Antarctica/VostokArctic/Longyearbyen',
				'Asia/Macau' => '(-00:00) Asia/Macau',
				'Asia/Makassar' => '(-00:00) Asia/Makassar',
				'Asia/Oral' => '(-00:00) Asia/Oral',
				'Asia/Qyzylorda' => '(-00:00) Asia/Qyzylorda',
				'Asia/YerevanAtlantic/Azores' => '(-00:00) Asia/YerevanAtlantic/Azores',
				'Australia/Currie' => '(-00:00) Australia/Currie',
				'Europe/Amsterdam' => '(+01:00) Europe/Amsterdam',
				'Europe/Andorra' => '(+01:00) Europe/Andorra',
				'Europe/Belgrade' => '(+01:00) Europe/Belgrade',
				'Europe/Berlin' => '(+01:00) Europe/Berlin',
				'Europe/Bratislava' => '(+01:00) Europe/Bratislava',
				'Europe/Brussels' => '(+01:00) Europe/Brussels',
				'Europe/Budapest' => '(+01:00) Europe/Budapest',
				'Europe/Copenhagen' => '(+01:00) Europe/Copenhagen',
				'Europe/Gibraltar' => '(+01:00) Europe/Gibraltar',
				'Europe/Ljubljana' => '(+01:00) Europe/Ljubljana',
				'Europe/Luxembourg' => '(+01:00) Europe/Luxembourg',
				'Europe/Madrid' => '(+01:00) Europe/Madrid',
				'Europe/Malta' => '(+01:00) Europe/Malta',
				'Europe/Monaco' => '(+01:00) Europe/Monaco',
				'Europe/Oslo' => '(+01:00) Europe/Oslo',
				'Europe/Paris' => '(+01:00) Europe/Paris',
				'Europe/Prague' => '(+01:00) Europe/Prague',
				'Europe/Rome' => '(+01:00) Europe/Rome',
				'Europe/San_Marino' => '(+01:00) Europe/San_Marino',
				'Europe/Sarajevo' => '(+01:00) Europe/Sarajevo',
				'Europe/Skopje' => '(+01:00) Europe/Skopje',
				'Europe/Stockholm' => '(+01:00) Europe/Stockholm',
				'Europe/Tirane' => '(+01:00) Europe/Tirane',
				'Europe/Vaduz' => '(+01:00) Europe/Vaduz',
				'Europe/Vatican' => '(+01:00) Europe/Vatican',
				'Europe/Vienna' => '(+01:00) Europe/Vienna',
				'Europe/Warsaw' => '(+01:00) Europe/Warsaw',
				'Europe/Zagreb' => '(+01:00) Europe/Zagreb',
				'Europe/Zurich' => '(+01:00) Europe/Zurich',
				'Africa/Ceuta' => '(+01:00) Africa/Ceuta',
				'Africa/Algiers' => '(+01:00) Africa/Algiers',
				'Africa/Bangui' => '(+01:00) Africa/Bangui',
				'Africa/Brazzaville' => '(+01:00) Africa/Brazzaville',
				'Africa/Douala' => '(+01:00) Africa/Douala',
				'Africa/Kinshasa' => '(+01:00) Africa/Kinshasa',
				'Africa/Lagos' => '(+01:00) Africa/Lagos',
				'Africa/Libreville' => '(+01:00) Africa/Libreville',
				'Africa/Luanda' => '(+01:00) Africa/Luanda',
				'Africa/Malabo' => '(+01:00) Africa/Malabo',
				'Africa/Ndjamena' => '(+01:00) Africa/Ndjamena',
				'Africa/Niamey' => '(+01:00) Africa/Niamey',
				'Africa/Porto-Novo' => '(+01:00) Africa/Porto-Novo',
				'Africa/Tunis' => '(+01:00) Africa/Tunis',
				'Africa/Windhoek' => '(+01:00) Africa/Windhoek',
				'Atlantic/Jan_Mayen' => '(+01:00) Atlantic/Jan_Mayen',
				'Europe/Bucharest' => '(+02:00) Europe/Bucharest',
				'Europe/Athens' => '(+02:00) Europe/Athens',
				'Europe/Chisinau' => '(+02:00) Europe/Chisinau',
				'Europe/Helsinki' => '(+02:00) Europe/Helsinki',
				'Europe/Istanbul' => '(+02:00) Europe/Istanbul',
				'Europe/Kaliningrad' => '(+02:00) Europe/Kaliningrad',
				'Europe/Kiev' => '(+02:00) Europe/Kiev',
				'Europe/Minsk' => '(+02:00) Europe/Minsk',
				'Europe/Nicosia' => '(+02:00) Europe/Nicosia',
				'Europe/Riga' => '(+02:00) Europe/Riga',
				'Europe/Simferopol' => '(+02:00) Europe/Simferopol',
				'Europe/Sofia' => '(+02:00) Europe/Sofia',
				'Europe/Tallinn' => '(+02:00) Europe/Tallinn',
				'Europe/Tiraspol' => '(+02:00) Europe/Tiraspol',
				'Europe/Uzhgorod' => '(+02:00) Europe/Uzhgorod',
				'Europe/Zaporozhye' => '(+02:00) Europe/Zaporozhye',
				'Europe/Vilnius' => '(+02:00) Europe/Vilnius',
				'Africa/Blantyre' => '(+02:00) Africa/Blantyre',
				'Africa/Bujumbura' => '(+02:00) Africa/Bujumbura',
				'Africa/Cairo' => '(+02:00) Africa/Cairo',
				'Africa/Gaborone' => '(+02:00) Africa/Gaborone',
				'Africa/Harare' => '(+02:00) Africa/Harare',
				'Africa/Johannesburg' => '(+02:00) Africa/Johannesburg',
				'Africa/Kigali' => '(+02:00) Africa/Kigali',
				'Africa/Lubumbashi' => '(+02:00) Africa/Lubumbashi',
				'Africa/Lusaka' => '(+02:00) Africa/Lusaka',
				'Africa/Maputo' => '(+02:00) Africa/Maputo',
				'Africa/Maseru' => '(+02:00) Africa/Maseru',
				'Africa/Mbabane' => '(+02:00) Africa/Mbabane',
				'Africa/Tripoli' => '(+02:00) Africa/Tripoli',
				'Asia/Amman' => '(+02:00) Asia/Amman',
				'Asia/Beirut' => '(+02:00) Asia/Beirut',
				'Asia/Damascus' => '(+02:00) Asia/Damascus',
				'Asia/Gaza' => '(+02:00) Asia/Gaza',
				'Asia/Istanbul' => '(+02:00) Asia/Istanbul',
				'Asia/Nicosia' => '(+02:00) Asia/Nicosia',
				'Asia/Tel_Aviv' => '(+02:00) Asia/Tel_Aviv',
				'Europe/Moscow' => '(+03:00) Europe/Moscow',
				'Africa/Asmera' => '(+03:00) Africa/Asmera',
				'Africa/Addis_Ababa' => '(+03:00) Africa/Addis_Ababa',
				'Africa/Dar_es_Salaam' => '(+03:00) Africa/Dar_es_Salaam',
				'Africa/Djibouti' => '(+03:00) Africa/Djibouti',
				'Africa/Kampala' => '(+03:00) Africa/Kampala',
				'Africa/Khartoum' => '(+03:00) Africa/Khartoum',
				'Africa/Mogadishu' => '(+03:00) Africa/Mogadishu',
				'Africa/Nairobi' => '(+03:00) Africa/Nairobi',
				'Antarctica/Syowa' => '(+03:00) Antarctica/Syowa',
				'Asia/Aden' => '(+03:00) Asia/Aden',
				'Asia/Baghdad' => '(+03:00) Asia/Baghdad',
				'Asia/Bahrain' => '(+03:00) Asia/Bahrain',
				'Asia/Kuwait' => '(+03:00) Asia/Kuwait',
				'Asia/Qatar' => '(+03:00) Asia/Qatar',
				'Asia/Riyadh' => '(+03:00) Asia/Riyadh',
				'Asia/Tehran' => '(+03:30) Asia/Tehran',
				'Indian/Antananarivo' => '(+03:00) Indian/Antananarivo',
				'Indian/Comoro' => '(+03:00) Indian/Comoro',
				'Indian/Mayotte' => '(+03:00) Indian/Mayotte',
				'Europe/Samara' => '(+04:00) Europe/Samara',
				'Asia/Aqtau' => '(+04:00) Asia/Aqtau',
				'Asia/Baku' => '(+04:00) Asia/Baku',
				'Asia/Dubai' => '(+04:00) Asia/Dubai',
				'Asia/Muscat' => '(+04:00) Asia/Muscat',
				'Asia/Tbilisi' => '(+04:00) Asia/Tbilisi',
				'Indian/Mahe' => '(+04:00) Indian/Mahe',
				'Indian/Mauritius' => '(+04:00) Indian/Mauritius',
				'Indian/Reunion' => '(+04:00) Indian/Reunion',
				'Asia/Kabul' => '(+04:30) Asia/Kabul',
				'Asia/Aqtobe' => '(+05:00) Asia/Aqtobe',
				'Asia/Ashgabat' => '(+05:00) Asia/Ashgabat',
				'Asia/Ashkhabad' => '(+05:00) Asia/Ashkhabad',
				'Asia/Bishkek' => '(+05:00) Asia/Bishkek',
				'Asia/Dushanbe' => '(+05:00) Asia/Dushanbe',
				'Asia/Karachi' => '(+05:00) Asia/Karachi',
				'Asia/Samarkand' => '(+05:00) Asia/Samarkand',
				'Asia/Tashkent' => '(+05:00) Asia/Tashkent',
				'Asia/Yekaterinburg' => '(+05:00) Asia/Yekaterinburg',
				'Indian/Kerguelen' => '(+05:00) Indian/Kerguelen',
				'Indian/Maldives' => '(+05:00) Indian/Maldives',
				'Asia/Calcutta' => '(+05:30) Asia/Calcutta',
				'Asia/Katmandu' => '(+05:45) Asia/Katmandu',
				'Antarctica/Mawson' => '(+06:00) Antarctica/Mawson',
				'Asia/Almaty' => '(+06:00) Asia/Almaty',
				'Asia/Colombo' => '(+06:00) Asia/Colombo',
				'Asia/Dacca' => '(+06:00) Asia/Dacca',
				'Asia/Dhaka' => '(+06:00) Asia/Dhaka',
				'Asia/Novosibirsk' => '(+06:00) Asia/Novosibirsk',
				'Asia/Omsk' => '(+06:00) Asia/Omsk',
				'Asia/Thimbu' => '(+06:00) Asia/Thimbu',
				'Asia/Thimphu' => '(+06:00) Asia/Thimphu',
				'Indian/Chagos' => '(+06:00) Indian/Chagos',
				'Asia/Rangoon' => '(+06:30) Asia/Rangoon',
				'Indian/Cocos' => '(+06:30) Indian/Cocos',
				'Antarctica/Davis' => '(+07:00) Antarctica/Davis',
				'Indian/Christmas' => '(+07:00) Indian/Christmas',
				'Asia/Bangkok' => '(+07:00) Asia/Bangkok',
				'Asia/Hovd' => '(+07:00) Asia/Hovd',
				'Asia/Jakarta' => '(+07:00) Asia/Jakarta',
				'Asia/Krasnoyarsk' => '(+07:00) Asia/Krasnoyarsk',
				'Asia/Phnom_Penh' => '(+07:00) Asia/Phnom_Penh',
				'Asia/Pontianak' => '(+07:00) Asia/Pontianak',
				'Asia/Saigon' => '(+07:00) Asia/Saigon',
				'Asia/Vientiane' => '(+07:00) Asia/Vientiane',
				'Antarctica/Casey' => '(+08:00) Antarctica/Casey',
				'Asia/Brunei' => '(+08:00) Asia/Brunei',
				'Asia/Chongqing' => '(+08:00) Asia/Chongqing',
				'Asia/Chungking' => '(+08:00) Asia/Chungking',
				'Asia/Harbin' => '(+08:00) Asia/Harbin',
				'Asia/Hong_Kong' => '(+08:00) Asia/Hong_Kong',
				'Asia/Irkutsk' => '(+08:00) Asia/Irkutsk',
				'Asia/Kashgar' => '(+08:00) Asia/Kashgar',
				'Asia/Kuala_Lumpur' => '(+08:00) Asia/Kuala_Lumpur',
				'Asia/Kuching' => '(+08:00) Asia/Kuching',
				'Asia/Macao' => '(+08:00) Asia/Macao',
				'Asia/Manila' => '(+08:00) Asia/Manila',
				'Asia/Shanghai' => '(+08:00) Asia/Shanghai',
				'Asia/Singapore' => '(+08:00) Asia/Singapore',
				'Asia/Taipei' => '(+08:00) Asia/Taipei',
				'Asia/Ujung_Pandang' => '(+08:00) Asia/Ujung_Pandang',
				'Asia/Ulaanbaatar' => '(+08:00) Asia/Ulaanbaatar',
				'Asia/Ulan_Bator' => '(+08:00) Asia/Ulan_Bator',
				'Asia/Urumqi' => '(+08:00) Asia/Urumqi',
				'Australia/Perth' => '(+08:00) Australia/Perth',
				'Australia/West' => '(+08:00) Australia/West',
				'Asia/Choibalsan' => '(+09:00) Asia/Choibalsan',
				'Asia/Dili' => '(+09:00) Asia/Dili',
				'Asia/Jayapura' => '(+09:00) Asia/Jayapura',
				'Asia/Pyongyang' => '(+09:00) Asia/Pyongyang',
				'Asia/Seoul' => '(+09:00) Asia/Seoul',
				'Asia/Tokyo' => '(+09:00) Asia/Tokyo',
				'Asia/Yakutsk' => '(+09:00) Asia/Yakutsk',
				'Pacific/Palau' => '(+09:00) Pacific/Palau',
				'Australia/Adelaide' => '(+09:30) Australia/Adelaide',
				'Australia/Broken_Hill' => '(+09:30) Australia/Broken_Hill',
				'Australia/Darwin' => '(+09:30) Australia/Darwin',
				'Australia/North' => '(+09:30) Australia/North',
				'Australia/South' => '(+09:30) Australia/South',
				'Australia/Yancowinna' => '(+09:30) Australia/Yancowinna',
				'Antarctica/DumontDUrville' => '(+10:00) Antarctica/DumontDUrville',
				'Asia/Sakhalin' => '(+10:00) Asia/Sakhalin',
				'Asia/Vladivostok' => '(+10:00) Asia/Vladivostok',
				'Australia/ACT' => '(+10:00) Australia/ACT',
				'Australia/Brisbane' => '(+10:00) Australia/Brisbane',
				'Australia/Canberra' => '(+10:00) Australia/Canberra',
				'Australia/Hobart' => '(+10:00) Australia/Hobart',
				'Australia/Lindeman' => '(+10:00) Australia/Lindeman',
				'Australia/Melbourne' => '(+10:00) Australia/Melbourne',
				'Australia/NSW' => '(+10:00) Australia/NSW',
				'Australia/Queensland' => '(+10:00) Australia/Queensland',
				'Australia/Sydney' => '(+10:00) Australia/Sydney',
				'Australia/Tasmania' => '(+10:00) Australia/Tasmania',
				'Australia/Victoria' => '(+10:00) Australia/Victoria',
				'Pacific/Guam' => '(+10:00) Pacific/Guam',
				'Pacific/Port_Moresby' => '(+10:00) Pacific/Port_Moresby',
				'Pacific/Saipan' => '(+10:00) Pacific/Saipan',
				'Pacific/Truk' => '(+10:00) Pacific/Truk',
				'Pacific/Yap' => '(+10:00) Pacific/Yap',
				'Australia/Lord_Howe' => '(+10:30) Australia/Lord_Howe',
				'Australia/LHI' => '(+10:30) Australia/LHI',
				'Asia/Magadan' => '(+11:00) Asia/Magadan',
				'Pacific/Efate' => '(+11:00) Pacific/Efate',
				'Pacific/Guadalcanal' => '(+11:00) Pacific/Guadalcanal',
				'Pacific/Kosrae' => '(+11:00) Pacific/Kosrae',
				'Pacific/Noumea' => '(+11:00) Pacific/Noumea',
				'Pacific/Ponape' => '(+11:00) Pacific/Ponape',
				'Pacific/Norfolk' => '(+11:30) Pacific/Norfolk',
				'Antarctica/McMurdo' => '(+12:00) Antarctica/McMurdo',
				'Antarctica/South_Pole' => '(+12:00) Antarctica/South_Pole',
				'Asia/Anadyr' => '(+12:00) Asia/Anadyr',
				'Asia/Kamchatka' => '(+12:00) Asia/Kamchatka',
				'Pacific/Auckland' => '(+12:00) Pacific/Auckland',
				'Pacific/Fiji' => '(+12:00) Pacific/Fiji',
				'Pacific/Funafuti' => '(+12:00) Pacific/Funafuti',
				'Pacific/Kwajalein' => '(+12:00) Pacific/Kwajalein',
				'Pacific/Majuro' => '(+12:00) Pacific/Majuro',
				'Pacific/Nauru' => '(+12:00) Pacific/Nauru',
				'Pacific/Tarawa' => '(+12:00) Pacific/Tarawa',
				'Pacific/Wake' => '(+12:00) Pacific/Wake',
				'Pacific/Wallis' => '(+12:00) Pacific/Wallis',
				'Pacific/Chatham' => '(+12:45) Pacific/Chatham',
				'Pacific/Enderbury' => '(+13:00) Pacific/Enderbury',
				'Pacific/Tongatapu' => '(+13:00) Pacific/Tongatapu',
				'Pacific/Kiritimati' => '(+14:00) Pacific/Kiritimati'
			];
		}

		/**
		 * Возвращает список идентификаторов пользователей,
		 * которые подписаны на уведомления о системных сообщениях.
		 * @return array
		 * @throws selectorException
		 */
		private function getSystemEventRecipients() {
			$auth = Service::Auth();
			$currentUserId = $auth->getUserId();

			$systemUsersPermissions = Service::SystemUsersPermissions();
			$svGroupId = $systemUsersPermissions->getSvGroupId();

			$sel = new selector('objects');
			$sel->types('object-type')->name('users', 'user');
			$sel->where('groups')->equals($svGroupId);

			$result = [$systemUsersPermissions->getSvUserId()];
			/** @var iUmiObject $user */
			foreach ($sel as $user) {
				if ($user->getId() != $currentUserId) {
					$result[] = $user->getId();
				}
			}
			return $result;
		}

		/**
		 * Возвращает путь до пакета обновлений
		 * @param string $name имя пакета (решения/расширения/модуля)
		 * @return string
		 */
		private function getDumpPath($name) {
			return CURRENT_WORKING_DIR . "/sys-temp/updates/$name/$name.xml";;
		}

		/** @internal */
		public function moveProfileLog() {
			static $moved;

			if ($moved) {
				return false;
			}

			$log = file_get_contents('umess://profile/inbox/');
			@file_put_contents('umess://profile/outbox/', json_encode($log));

			return $moved = true;
		}
	}
