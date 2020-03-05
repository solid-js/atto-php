<?php

class _
{
    /**
     * Shorthand to get Application data from path.
     * @param string $path Path like "config.version"
     * @param mixed $default Default value if data not found
     * @return mixed|null
     */
    public static function get ( $path, $default = null )
    {
        return App::instance()->get( $path, $default );
    }

    /**
     * Shorthand to safely parse data as HTML or JSON.
     * Can also parse content if $getPath is set to false and content
     * @param string $pathOrContent Path of data to get, or content to parse if $getPath is set to false.
     * @param int $level Level of data parsing.
     * @param bool $getPath If false, will print $path and not retrieve data from it.
     * @param string $append
     * @return false|mixed|string|null
     */
    public static function html ( $pathOrContent, $level = 1, $getPath = true, $append = null )
    {
        // Get content from path
        $content = ( $getPath ? _::get( $pathOrContent ) : $pathOrContent );

        if ( $level == 1 )
            return htmlentities( $content );

        else if ( $level == 2 )
            return htmlspecialchars( $content );

        else if ( $level == 3 )
            return json_encode( $content, JSON_NUMERIC_CHECK );

        if (!is_null($append))
            $content .= $append;

        return $content;
    }

    /**
     * Dump some raw data to the browser.
     * @param mixed $what Raw data to print.
     * @param mixed $exit Quit code.
     */
    public static function dump ( $what, $exit = 0 )
    {
        echo '<pre>';
        var_dump( $what );
        echo '</pre>';
        $exit && exit;
    }

    /**
     * Generate a link to an App resource
     * @param string $to Path to requested resource
     * @param bool $absolute Prefix with absolute path
     * @param bool $version Append with cache busting version like "?0.1"
     * @return mixed|string|null Relative or absolute link to resource
     */
    public static function href ( $to, $absolute = false, $version = false )
    {
        // Absolute path
        $href = ( $absolute ? _::get('config.host') : '' );

        // Add base
        $href .= _::get('config.base');

        // Add path to resource
        $href .= ltrim( $to, '/' );

        // Add version cache busting
        if ( $version ) $href .= '?'._::get('config.version');

        // Return generated href to resource
        return $href;
    }

    /**
     * Render a template, located in app/templates.
     * Will append .template.php to the path.
     * @param string $path Path to the template, without extension.
     * @param array $vars Vars to pass to the template
     */
    public static function render ( $path, $vars = [] )
    {
        require( APP_ROOT.'views/'.$path.'.template.php' );
    }

    /**
     * Print json with correct content-type headers.
     * @param mixed $data Data to print.
     * @param int $exit Exit script after json.
     */
    public static function json ( $data, $exit = 0 )
    {
        header('Content-type: application/json');
        print json_encode( $data, JSON_NUMERIC_CHECK );
        $exit && exit;
    }
}

