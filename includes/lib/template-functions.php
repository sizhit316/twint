<?php
/**
 * Locate template.
 *
 * Locate the called template.
 * Search Order:
 * 1. /themes/theme/templates/$template_name
 * 2. /themes/theme/$template_name
 * 3. /plugins/plugin/templates/$template_name.
 *
 * @since 2.2.0
 *
 * @param   string  $template_name          Template to load.
 * @param   string  $template_path          Path to templates.
 * @param   string  $default_path           Default path to template files.
 * @return  string                          Path to the template file.
 */
function mame_twint_locate_template( $template_name, $template_path = '', $default_path = '' ) {

    // Set variable to search in the templates folder of theme.
    if ( ! $template_path ) :
        $template_path = 'templates/';
    endif;

    // Set default plugin templates path.
    if ( ! $default_path ) :
        $default_path = MAME_TW_TEMPLATES_PATH; // Path to the template folder
    endif;

    // Search template file in theme folder.
    $template = locate_template( array(
        $template_path . $template_name,
        $template_name
    ) );

    // Get plugins template file.
    if ( ! $template ) :
        $template = $default_path . $template_name;
    endif;

    return apply_filters( 'mame_twint_locate_template', $template, $template_name, $template_path, $default_path );

}

/**
 * Get template.
 *
 * Search for the template and include the file.
 *
 * @since 2.2.0
 *
 * @see mame_twint_locate_template()
 *
 * @param string  $template_name          Template to load.
 * @param array   $args                   Args passed for the template file.
 * @param string  $template_path          Path to templates.
 * @param string  $default_path           Default path to template files.
 */
function mame_twint_get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {

    if ( is_array( $args ) && isset( $args ) ) :
        extract( $args );
    endif;

    $template_file = mame_twint_locate_template( $template_name, $template_path, $default_path );

    if ( ! file_exists( $template_file ) ) :
        _doing_it_wrong( __FUNCTION__, sprintf( '<code>%s</code> does not exist.', $template_file ), '1.0.0' );
        return;
    endif;

    include $template_file;

}

/**
 * Template loader.
 *
 * The template loader will check if WP is loading a template
 * for a specific Post Type and will try to load the template
 * from out 'templates' directory.
 *
 * @since 1.0.0
 *
 * @param string  $template Template file that is being loaded.
 * @return  string          Template file that should be loaded.
 */
/*
function mame_twint_template_loader( $template ) {

    $file = '';

    if( is_singular() ):
        $file = 'single-plugin.php';
    elseif( is_tax() ):
        $file = 'archive-plugin.php';
    endif;

    if ( file_exists( mame_twint_locate_template( $file ) ) ) :
        $template = mame_twint_locate_template( $file );
    endif;

    return $template;

}
add_filter( 'template_include', 'mame_twint_template_loader' );
*/