<?php

// Import helpers and utilities
require_once( APP_ROOT.'utils/ArrayUtils.php' );
require_once( APP_ROOT.'utils/UnderscoreHelper.php' );

class App
{
    // ------------------------------------------------------------------------- SINGLETON

    /** @var App */
    protected static $__instance;

    /**
     * @return App
     */
    public static function instance ()
    {
        // Create instance for the first time
        if ( is_null(self::$__instance) )
            self::$__instance = new App();
        return self::$__instance;
    }


    // ------------------------------------------------------------------------- CONSTRUCT

    public function __construct ()
    {
        // First, always init required data
        $this->initData();
    }

    // ------------------------------------------------------------------------- DATA

    /** @var mixed */
    protected $_data;
    public function data () { return $this->_data; }

    /**
     * Load a text based file.
     * @param string $path Path to text file, relative to "data/" and without extension
     * @param string $extension Default extension is txt.
     * @return string File content
     */
    public function loadText ( $path, $extension = 'txt' )
    {
        return file_get_contents( APP_ROOT.'data/'.$path.'.'.$extension );
    }

    /**
     * Load a JSON file
     * @param string $path Path to JSON file, relative to "data/" and without extension
     * @param boolean $allowComments Allow JSON file to contains comments, like any regular JS file.
     * @return mixed JSON parsed data as associative array.
     */
    public function loadJSON ( $path, $allowComments = true )
    {
        // Load raw data from json
        $text = $this->loadText( $path, 'json');

        // Strip comments
        if ( $allowComments )
            $text = preg_replace( '![ \t]*//.*[ \t]*[\r\n]!', '', $text );

        // Decode data as array
        return json_decode( $text, true );
    }

    /**
     * Init and load Application data.
     * Locales are not loaded yet at this point.
     */
    protected function initData ()
    {
        $this->_data = [
            'config'    => $this->loadJSON('config'),
            'routes'    => $this->loadJSON('routes')
        ];

        // Load current phase data
        //$this->_data['phase']['data'] = $this->loadJSON( 'phases/'.$this->_data['phase']['currentPhase'] );

        //_::dump($this->_data, 1);
    }

    /**
     * Get Application data from path.
     * Use "." to return all data.
     * @param string $path Path like "config.version"
     * @param mixed $default Default value if data not found
     * @return mixed|null
     */
    public function get ( $path, $default = null )
    {
        if ( $path == '.' ) return $this->_data;
        $value = ArrayUtils::traverse( $path, $this->_data );
        return is_null( $value ) ? $default : $value;
    }

    // ------------------------------------------------------------------------- ROUTES

    /**
     * Requested path parts, as lowercase.
     * @var string[]
     */
    protected $_paths;
    public function paths () { return $this->_paths; }

    /**
     * Start application.
     * @param string $path Requested path from server. No leading slash.
     */
    public function start ($path = '' )
    {
        // Split paths on every slash, no capital case, no trailing slash
        $this->_paths = explode('/', rtrim(strtolower($path), '/'));
        //_::dump( $this->_paths, 1 );

        // Prepare routes
        foreach ( $this->_data['routes']['routes'] as $routePath => &$route )
        {
            // Remove optional trailing slash
            $routePath = rtrim($routePath, '/');

            // Search for parameters in route
            $matches = [];
            preg_match_all("/\{([a-z0-9\-\_]+)\}/", $routePath, $matches);

            if (
                // If we are in a multi locale configuration
                $this->_data['routes']['locales']['multi']
                // And if this route has a locale
                && (
                    !isset($route['locale']) || $route['locale'] !== false
                )
            ) {
                // Prepend route detection with the locale code
                $matches[1] = array_merge(['locale'], $matches[1]);
                $routePath = "/{locale}".$routePath;
            }

            // Store route real path
            $route['realPath'] = $routePath;

            // Replace slashes to escape them for regex
            $matcher = str_replace("/", "\/", $routePath);
            $matcher = str_replace("{", "", $matcher);
            $matcher = str_replace("}", "", $matcher);

            // Replace all parameter placeholder by its regex equivalent
            foreach ( $matches[1] as $match )
                $matcher = str_replace("$match", "([a-z0-9\-\_]+)", $matcher);

            // Store the regex matcher into the route object
            $route['matcher'] = "/^$matcher\/?$/";

            // Save route parameters names
            $route['parameters'] = $matches[1];
        }
        //_::dump($this->_data['routes']['routes'], 1);

        // Only if we are in multi locale mode
        if ( $this->get('routes.locales.multi') )
        {
            // Get no multi locale route
            $route = $this->searchRoute( true );

            // And execute it if we have one
            if ( !is_null($route) )
            {
                $this->route($route);
                return;
            }
        }


        // Select locale from path or browser, redirect or load locale content
        $this->initLocale();
    }

    /**
     * TODO DOC
     * @param bool $onlyNoLocale
     * @return |null
     */
    protected function searchRoute ( $onlyNoLocale = false )
    {
        // Compute current requested path
        $currentPath = '/'.implode( '/', $this->_paths );
        //_::dump($this->_data['routes']['routes'], 1);
        _::dump('--- '.$onlyNoLocale);
        // Browse routes
        foreach ( $this->_data['routes']['routes'] as $path => $route )
        {
            _::dump($path);
            // Filter no locale routes
            if ( $onlyNoLocale && isset($route['locale']) && $route['locale'] == false ) continue;

            // Get matching routes
            $matches = [];
            preg_match($route['matcher'], $currentPath, $matches);

            // Count parameters and select only if correct
            if ( count($matches) != count($route['parameters']) + 1 ) continue;
            return $route;
        }
        return null;
    }

    /**
     * TODO
     * @param $route
     */
    public function route ( $route )
    {
        _::dump('ROUTE');
        _::dump($route, 1);
    }

    // ------------------------------------------------------------------------- LOCALE

    /**
     * Get locale from path and load locale data if available.
     * Otherwise, get requested locale from cookie or browser headers and redirect.
     */
    protected function initLocale ()
    {
        // Get available locales list
        $localesFiles = scandir(APP_ROOT.'data/locales/');
        $availableLocales = [];
        foreach ( $localesFiles as $file )
        {
            if ($file == '.' || $file == '..') continue;
            $availableLocales[] = pathinfo($file, PATHINFO_FILENAME);
        }
        //_::dump($availableLocales, 1);

        // Get locale separator
        $localeSeparator = $this->get('routes.locales.separator', '-');

        // Get requested locale from path
        $localeCodeFromPath = $this->_paths[0];
        $splitLocaleCodeFromPath = explode( $localeSeparator, $localeCodeFromPath, 2 );

        // This is an API link
        if ( $localeCodeFromPath == $this->get('routes.services') )
        {
            $this->initServices();
            return;
        }

        // Get locale cookie name
        $cookieName = $this->get('routes.locales.cookie');

        // If this locale + country code exists
        if ( count($splitLocaleCodeFromPath) === 2 && in_array($localeCodeFromPath, $availableLocales) )
        {
            $selectedLocaleCode = $splitLocaleCodeFromPath[0].$localeSeparator.$splitLocaleCodeFromPath[1];

            // Set locale cookie
            if ( !is_null($cookieName) ) setcookie($cookieName, $selectedLocaleCode);

            // Continue with this code
            $this->loadLocale(
                $selectedLocaleCode,
                '/'.implode('/', $this->_paths)
            );

            // Get no multi locale route
            $route = $this->searchRoute();

            // And execute it if we have one
            if ( !is_null($route) )
            {
                $this->route($route);
                return;
            }


            // TODO - 404
            die('404');
        }

        // Select locale from swatch cookie.
        if ( !is_null($cookieName) && isset($_COOKIE[$cookieName]) && in_array($_COOKIE[$cookieName], $availableLocales) )
            $preferredAvailableLocale = $_COOKIE[$cookieName];

        else
        {
            // No locale from path, get the locale from browser
            $isoSplit = explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), 2)[0];

            // Locale test zone :
            //$isoSplit = 'en-gb';      // -> en_gb
            //$isoSplit = 'fr-fr';      // -> fr_fr
            //$isoSplit = 'en-us';      // -> en_gb
            //$isoSplit = 'fr-ca';      // -> fr_fr
            //$isoSplit = 'zh-cn';      // -> en_gb

            // Split locale and country from dashed iso
            list( $userLocale, $userCountry ) = explode('-', strtolower($isoSplit));

            // Browse available locales
            foreach ( $availableLocales as $localeAndCountryCode )
            {
                // Get iso locale and country codes
                list( $availableLocale, $availableCountry ) = explode($localeSeparator, $localeAndCountryCode);

                // Select nearest locale from country or locale code
                if ( !isset($preferredAvailableLocale) )
                {
                    if ( $availableLocale == $userLocale )
                        $preferredAvailableLocale = $localeAndCountryCode;

                    if ( $availableCountry == $userCountry )
                        $preferredAvailableLocale = $localeAndCountryCode;
                }

                // But prefer exact matches
                if ( $availableCountry == $userCountry && $availableLocale == $userLocale )
                    $preferredAvailableLocale = $localeAndCountryCode;
            }

            // We select the first locale in the available list as default
            // if no compatible locale has been found
            if ( !isset($preferredAvailableLocale) )
                $preferredAvailableLocale = $this->get('routes.locales.default');
        }

        // Redirect user to this locale + country content
        $redirectDestination = $this->get('config.base').$preferredAvailableLocale.'/';
        //_::dump(['REDIRECT TO', $redirectDestination], 1);
        header("Location: ${redirectDestination}");
    }

    /**
     * Load application locale data
     * @param string $locale Locale ISO code like en_gb
     * @param string $page Requested page, with leading slash
     */
    public function loadLocale ( $locale, $page )
    {
        // Inject locale code / language and country into locale object
        $localeObject = &$this->_data['locale'];
        $localeObject['currentCode'] = $locale;
        $localeSeparator = $this->get('routes.locales.separator', '-');
        $splitLocale = explode($localeSeparator, $locale);
        $localeObject['currentLanguage'] = $splitLocale[0];
        $localeObject['currentCountry'] = $splitLocale[1];

        // Inject locale data
        $localeObject['data'] = $this->loadJSON( 'locales/'.$locale );

        // Inject page name into app data
        $this->_data['page'] = $page;

        // Inject meta and merge with global meta
        $this->_data['meta'] = $this->getPageMeta( $page );

        // Not meta found
        if ( is_null($this->_data['meta']) && $page != '/choose' )
        {
            // Get home meta
            $this->_data['meta'] = $this->getPageMeta('/', true);

            // And add 404 page type
            $this->_data['meta']['pageType'] = '404';
        }

        // Generate canical URL
        $this->_data['meta']['url'] = $this->_data['config']['host'].$this->_data['config']['base'].$locale.$page;

        //_::dump($this->_data, 1);
    }

    /**
     * Get meta for page from its path, with leading slash.
     * Will check meta in phase first then global meta.
     * @param string $page Page path, leading slash (like /my-page.html)
     * @param bool $emptyArrayIfNotFound
     * @return mixed|null Array of meta from current locale
     */
    protected function getPageMeta ( $page, $emptyArrayIfNotFound = false )
    {
        $metas = &$this->_data['locale']['data']['metas'];
        // TODO : PHASE
        //$phase = $this->_data['phase']['currentPhase'];
        $phase = "";

        // Get page meta from phase specific first
        if ( isset($metas[ $phase ][ $page ]) )
            return $metas[ $phase ][ $page ];

        // Try without phase otherwise
        else if ( isset($metas[ $page ]) )
            return $metas[ $page ];

        // Ne meta found
        else return ( $emptyArrayIfNotFound ? [] : null );
    }

    // ------------------------------------------------------------------------- ACTIONS

    public function call ( $type, $name, $action, $parameters = [] )
    {
        // TODO
    }


    // ------------------------------------------------------------------------- SERVICES

    /**
     * Initialize services
     */
    protected function initServices ()
    {
        // Do not respond if we do not have enough info to target service and method
        if ( !isset($this->_paths[2]) ) _::json(['code' => 0], 1);

        // Get service and method names
        $serviceName = strtolower( $this->_paths[1] );
        $methodName = $this->_paths[2];

        // Kick anything that is not purely alphanumeric to avoid hacks
        if ( !ctype_alnum($serviceName) ) _::json(['code' => 1], 1);

        // Load locale if we have it as a GET parameter
        // Locale is in GET here to avoid complex config on Akamai
        if ( isset($_GET['locale']) )
            $this->loadLocale( $_GET['locale'], '/'.$this->_paths[0] );

        // Service PHP path, secured
        $servicePath = APP_ROOT.'services/'.$serviceName.'.php';

        // Service not found
        if ( !file_exists($servicePath) ) _::json(['code' => 2], 1);

        // Require and try to instantiate class
        require_once( $servicePath );
        $className = ucfirst($serviceName).'Service';
        try
        {
            // FIXME : Error not handled ?
            $serviceInstance = new $className();
        }
        catch ( Exception $e ) { _::json(['code' => 3], 1); }

        // Third param passed directly to the method
        $param = ( isset($this->_paths[3]) ? $this->_paths[3] : null );
        $result = call_user_func_array([$serviceInstance, $methodName], [$param]);

        // Show returned object as JSON
        if ( !is_null($result) ) _::json( $result, 1 );
    }
}