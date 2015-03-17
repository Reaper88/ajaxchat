<?php

/**
*
* Ajax Chat extension for phpBB.
*
* @copyright (c) 2015 spaceace <http://www.livemembersonly.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace spaceace\ajaxchat\acp;

class ajaxchat_module
{

    /** @var string The currenct action */
    public $u_action;

    /** @var \phpbb\config\config */
    public $new_config = array();

    /** @var string form key */
    public $form_key;

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\user */
    protected $user;

    /** @var \phpbb\template\template */
    protected $template;

    /** @var \phpbb\request\request */
    protected $request;

    public function main($id, $mode)
    {
        global $phpbb_container;

        // Initialization
        $this->config   = $phpbb_container->get('config');
        $this->db       = $phpbb_container->get('dbal.conn');
        $this->user     = $phpbb_container->get('user');
        $this->template = $phpbb_container->get('template');
        $this->request  = $phpbb_container->get('request');

        $this->u_action = $this->request->variable('action', '', true);
        $submit         = ($this->request->is_set_post('submit')) ? true : false;
        $this->form_key = 'acp_ajax_chat';
        add_form_key($this->form_key);

        $display_vars = array(
			'title' => 'ACP_AJAX_CHAT_TITLE',
			'vars'  => array(
				'legend1'					=> 'AJAX_CHAT_SETTINGS',
				'display_ajax_chat'			=> array('lang' => 'DISPLAY_AJAX_CHAT', 'validate' => 'bool', 'type' => 'radio:enabled_enabled', 'explain' => false),
				'index_display_ajax_chat'	=> array('lang' => 'INDEX_DISPLAY_AJAX_CHAT', 'validate' => 'bool', 'type' => 'radio:enabled_enabled', 'explain' => true),
				'whois_chatting'			=> array('lang' => 'WHOIS_CHATTING', 'validate' => 'bool', 'type' => 'radio:enabled_enabled', 'explain' => true),
				'ajax_chat_forum_posts'		=> array('lang'	=> 'FORUM_POSTS_AJAX_CHAT', 'validate' => 'bool', 'type' => 'radio:enabled_enabled', 'explain' => false),
				'refresh_ajax_chat'			=> array('lang' => 'REFRESH_AJAX_CHAT', 'validate' => 'int', 'type' => 'number:0:9999', 'explain' => false),
				'rule_ajax_chat'			=> array('lang' => 'RULE_AJAX_CHAT', 'validate' => 'string', 'type' => 'text:40:255', 'explain' => true),
				'ajax_chat_archive_amount'	=> array('lang'	=> 'ARCHIVE_AMOUNT_AJAX_CHAT', 'validate' => 'int', 'type' => 'number:5:500', 'explain' => true),
				'ajax_chat_popup_amount'	=> array('lang'	=> 'POPUP_AMOUNT_AJAX_CHAT', 'validate' => 'int', 'type' => 'number:5:150', 'explain' => true),
				'ajax_chat_index_amount'	=> array('lang'	=> 'INDEX_AMOUNT_AJAX_CHAT', 'validate' => 'int', 'type' => 'number:5:150', 'explain' => true),
				'ajax_chat_chat_amount'		=> array('lang'	=> 'CHAT_AMOUNT_AJAX_CHAT', 'validate' => 'int', 'type' => 'number:5:150', 'explain' => true),
				'ajax_chat_time_setting'	=> array('lang' => 'TIME_SETTING_AJAX_CHAT', 'validate' => 'string', 'type' => 'text:10:20', 'explain' => true),
				
				'legend2'					=> 'ACP_SUBMIT_CHANGES'
			),
		);

		#region Submit
		if ($submit)
		{
			$submit = $this->do_submit_stuff($display_vars);

			// If the submit was valid, so still submitted
			if ($submit)
			{
				trigger_error($this->user->lang('CONFIG_UPDATED') . adm_back_link($this->u_action), E_USER_NOTICE);
			}
		}
		#endregion

		$this->generate_stuff_for_cfg_template($display_vars);

		// Output page template file
		$this->tpl_name   = 'ajax_chat';
		$this->page_title = $this->user->lang($display_vars['title']);
	}

	/**
	 * Abstracted method to do the submit part of the acp. Checks values, saves them
	 * and displays the message.
	 * If error happens, Error is shown and config not saved. (so this method quits and returns false.
	 * 
	 * @param array $display_vars The display vars for this acp site
	 * @param array $special_functions Assoziative Array with config values where special functions should run on submit instead of simply save the config value. Array should contain 'config_value' => function ($this) { function code here }, or 'config_value' => null if no function should run.
	 * @return bool Submit valid or not.
	 */
	protected function do_submit_stuff($display_vars, $special_functions = array())
	{
		$this->new_config = $this->config;
		$cfg_array		= ($this->request->is_set('config')) ? $this->request->variable('config', array('' => ''), true) : $this->new_config;
		$error			= isset($error) ? $error : array();

		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if (!check_form_key($this->form_key))
		{
			$error[] = $this->user->lang['FORM_INVALID'];
		}

		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
			return false;
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			// We want to skip that, or run the function. (We do this before checking if there is a request value set for it,
			// cause maybe we want to run a function anyway, based on whatever. We can check stuff manually inside this function)
			if (is_array($special_functions) && array_key_exists($config_name, $special_functions))
			{
				$func = $special_functions[$config_name];
				if (isset($func) && is_callable($func))
					$func();

				continue;
			}

			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			// Sets the config value
			$this->new_config[$config_name] = $cfg_array[$config_name];
			$this->config->set($config_name, $cfg_array[$config_name]);
		}

		return true;
	}

	/**
	 * Abstracted method to generate acp configuration pages out of a list of display vars, using
	 * the function build_cfg_template().
	 * Build configuration template for acp configuration pages
	 * 
	 * @param array $display_vars The display vars for this acp site
	 */
	protected function generate_stuff_for_cfg_template($display_vars)
	{
		$this->new_config = $this->config;
		$cfg_array		= ($this->request->is_set('config')) ? $this->request->variable('config', array('' => ''), true) : $this->new_config;
		$error			= isset($error) ? $error : array();

		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$this->template->assign_block_vars('options', array(
					'S_LEGEND' => true,
					'LEGEND'   => (isset($this->user->lang[$vars])) ? $this->user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($this->user->lang[$vars['lang_explain']])) ? $this->user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			} else if ($vars['explain'])
			{
				$l_explain = (isset($this->user->lang[$vars['lang'] . '_EXPLAIN'])) ? $this->user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);

			if (empty($content))
			{
				continue;
			}

			$this->template->assign_block_vars('options', array(
				'KEY'			=> $config_key,
				'TITLE'			=> (isset($this->user->lang[$vars['lang']])) ? $this->user->lang[$vars['lang']] : $vars['lang'],
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $l_explain,
				'CONTENT'		=> $content,
			));

			//unset($display_vars['vars'][$config_key]);
		}

		$this->template->assign_vars(array(
			'S_ERROR'	=> (sizeof($error)) ? true : false,
			'ERROR_MSG'	=> implode('<br />', $error),
			'U_ACTION'	=> $this->u_action)
		);
	}
}
