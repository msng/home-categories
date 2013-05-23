<?php
/*
Plugin Name: Home Categories
Plugin URI: http://www.msng.info/
Description: Filters the categories of posts shown on your front page.
Author: msng
Version: 0.1
Author URI: http://www.msng.info/
Text Domain: home-categories
Domain Path: /languages/

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html
  Copyright 2013 Masunaga Ray (email : ray@msng.info)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class HomeCategories
{
	public $option_name;
	public $textdomain = 'home-categories';
	public $nonceName = '_wpnonce';

	public $dirname, $basename, $dir_url;
	public $admin_action;

	public $modes;
	public $message;

	public $defaults = array(
		'mode' => 'stop',
		'categories' => array(),
	);

	public function __construct() {
		include 'config.php';
		$this->option_name = $config['option_name'];

		$this->dirname = basename(dirname(__FILE__));
		$this->basename = plugin_basename(__FILE__);
		$this->dir_url = plugins_url($this->dirname);
		$this->admin_action = admin_url('admin.php?page=' . $this->basename);

		load_plugin_textdomain($this->textdomain, false, $this->dirname . '/languages/');

		$this->modes = array(
			'pick' => __('Show selected categories only'),
			'drop' => __('Hide selected categories'),
			'stop' => __('Do nothing'),
		);
	
		add_action('pre_get_posts', array(&$this, 'filter'));
		if ( is_admin() ) {
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_filter('plugin_action_links', array(&$this, 'action_links'), 10, 2 );
		}
	}

	public function filter( $query ) {
		if ( is_home() && $query->is_main_query() ) {
			$options = $this->_getOption();
			if ( $options['mode'] != 'stop' ) {
				if ( $options['mode'] == 'drop' ) {
					foreach ( $options['categories'] as $key => $cat_ID ) {
						$options['categories'][$key] = '-' . $cat_ID;
					}
				}
				$cat_IDs_str = implode(', ', $options['categories']);
				$query->set('cat', $cat_IDs_str);
			}
		}
	}

	public function admin_menu() {
		add_options_page('Home Categories', 'Home Categories', 'manage_options', __FILE__, array(&$this, 'options_page'));
		add_action('admin_head',  array(&$this, 'add_admin_style'));
	}

	public function action_links($links, $file) {
		if ($file == $this->basename) {
			$settings_link = '<a href="' . $this->admin_action . '">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link);
		}
		return $links;
	}

	public function options_page() {
		$out = '';
		$nonce = wp_create_nonce();

		if ( ! empty($_POST) ) {
			if ( $this->_validatePost() ) {
				$options = $this->_mergeDefaults($_POST);
				update_option($this->option_name, $options);
				$this->message = array(
					'class' => 'updated',
					'text' => __('Settings saved.', $this->textdomain),
				);
			}
		}

		if ( ! isset($options) ) {
			$options = $this->_getOption();
		}

		$out .= '<div class="wrap" id="home_categories_option">';

		$out .= '<div id="icon-options-general" class="icon32"><br /></div>';
		$out .= '<h2>Home Categories Options</h2>';
		$out .= '<p>' . __('Filters the categories of posts shown on your front page.', $this->textdomain) . '</p>';

		if ( !empty($this->message) ) {
			$out .= $this->_getMessage();
		}

		$out .= '<form method="post" id="update_options" action="' . $this->admin_action . '">'."\n";
		$out .= wp_nonce_field(-1, $this->nonceName, true, false);

		$out .= '<fieldset>';
		$out .= '<h3>' . __('Filter mode', $this->textdomain) . '</h3>';
		$out .= '<ul>';

		foreach ( $this->modes as $mode => $label ) {
			if ( $options['mode'] == $mode ) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}

			$out .= '<li>';
			$out .= '<input type="radio" name="mode" value="' . $mode . '" id="mode-' . $mode . '"' . $checked . ' />';
			$out .= '<label for="mode-' . $mode . '">' . __($label, $this->textdomain) . '<label>';
			$out .= '</li>';
		}

		$out .= '</ul>';
		$out .= '</fieldset>';

		$out .= '<h3>' . __('Categories', $this->textdomain) . '</h3>';
		$out .= '<ul>';

		$categories = get_categories();
		foreach ( $categories as $category ) {
			$out .= '<li>';
			if ( in_array($category->term_id, $options['categories']) ) {
				$checked = ' checked="checked"';
			} else {
				$checked = '';
			}
			$out .= '<input type="checkbox" name="categories[]" value="' . $category->term_id . '" id="cat_' . $category->term_id . '"' . $checked . ' />';
			$out .= '<label for="cat_' . $category->term_id . '">' . esc_attr($category->name) . '</label>';
			$out .= '</li>';
		}
		$out .= '</ul>';
		$out .= '<input type="submit" class="button button-primary" value="' . __('Save Changes', $this->textdomain) . '" />';
		$out .= '</form>';
		$out .= '</div>';
		echo $out;
	}

	public function add_admin_style() {
		echo '<link rel="stylesheet" type="text/css" href="' . $this->dir_url . '/css/home-categories.css" />';
	}

	protected function _validatePost() {
		if ( empty($_POST) || ! $this->_validateOptions($_POST) || ! $this->_verifyNonce() ) {
			$this->_setError(__('Invalid post.', $this->textdomain));
			return false;
		}

		if ($this->_isPickWithoutCategories($_POST)) {
			$this->_setError(__('Please check one or more categories to "Show selected categories only."', $this->textdomain));
			return false;
		}

		return true;
	}

	protected function _setError($text) {
		$this->message = array(
			'class' => 'error',
			'text' => $text,
		);
	}

	protected function _isPickWithoutCategories($data) {
		if ( ! empty($data['mode']) && $data['mode'] == 'pick' ) {
			if ( empty($data['categories']) ) {
				return true;
			}
		}
		return false;
	}

	protected function _validateOptions($options) {
		if ( isset($options['mode']) ) {
			if ( array_key_exists($options['mode'], $this->modes) ) {
				if ( ! isset($options['categories']) || is_array($options['categories']) ) {
					return true;
				}
			}
		}
		return false;
	}

	protected function _verifyNonce() {
		if ( ! empty($_POST[$this->nonceName]) ) {
			if ( wp_verify_nonce($_POST[$this->nonceName]) ) {
				return true;
			}
		}
		return false;
	}

	protected function _getOption() {
		$options = get_option($this->option_name);
		return $this->_mergeDefaults($options, $options);
	}

	protected function _mergeDefaults($data) {
		if ( ! is_array($data) ) {
			$data = array();
		}
		return array_merge($this->defaults, $data);
	}

	protected function _getMessage() {
		if ( empty($this->message) ) {
			return false;
		}

		if ( is_string($this->message) ) {
			$class = 'updated';
			$text = $this->message;
		} else {
			if ( empty($this->message['text']) ) {
				return false;
			}
			$text = $this->message['text'];

			if ( empty($this->message['class']) ) {
				$class = 'updated';
			} else {
				$class = $this->message['class'];
			}
		}

		return '<div id="message" class="' . $class . '"><p>' . $text . '</p></div>';
	}

}

$home_categories = new HomeCategories;

