<?php

/*
	Question2Answer (c) Gideon Greenspan

	http://www.question2answer.org/

	
	File: qa-include/qa-page-register.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Controller for register page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	require_once QA_INCLUDE_DIR.'qa-app-captcha.php';
	require_once QA_INCLUDE_DIR.'qa-db-users.php';


//	Check we're not using single-sign on integration, that we're not logged in, and we're not blocked

	if (QA_FINAL_EXTERNAL_USERS)
		qa_fatal_error('User registration is handled by external code');
		
	if (qa_is_logged_in())
		qa_redirect('');
	
	if (qa_opt('suspend_register_users')) {
		$qa_content=qa_content_prepare();
		$qa_content['error']=qa_lang_html('users/register_suspended');
		return $qa_content;
	}
	
	if (qa_user_permit_error()) {
		$qa_content=qa_content_prepare();
		$qa_content['error']=qa_lang_html('users/no_permission');
		return $qa_content;
	}
        
        
//	Process submitted form

	if (qa_clicked('doregister')) {
		require_once QA_INCLUDE_DIR.'qa-app-limits.php';
		
		if (qa_limits_remaining(null, QA_LIMIT_REGISTRATIONS)) {
			require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
			
			$role=qa_post_text('role');
                        
                        if($role == 'user') qa_redirect('registerasuser');
                        else if($role == 'expert') qa_redirect('registerasexpert');
			
		} else
			$pageerror=qa_lang('users/register_limit');
	}


//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']="Register as :";
	
	$qa_content['error']=@$pageerror;

	$custom=qa_opt('show_custom_register') ? trim(qa_opt('custom_register')) : '';
	
	$qa_content['form']=array(
		'tags' => 'METHOD="POST" ACTION="'.qa_self_html().'"',
		
		'style' => 'tall',
		
		'fields' => array(
			
                        
                        'radio' => array(
                                'label'=>'Select your Role',
                                'type' => 'select',
                                'tags' => 'NAME="role" ID="role"',
                                'value' => 'user',
                                'options' => array ('expert' => 'expert' ,'user' => 'user'),    
                                                    
                        ),
                        
		),
		
		'buttons' => array(
			'register' => array(
				'label' => "CONTINUE",
			),
		),
		
		'hidden' => array(
			'doregister' => '1',
		),
	);
	
	if (!strlen($custom))
		unset($qa_content['form']['fields']['custom']);
	
	if (qa_opt('captcha_on_register'))
		qa_set_up_captcha_field($qa_content, $qa_content['form']['fields'], @$errors);
	
	$loginmodules=qa_load_modules_with('login', 'login_html');
	
	foreach ($loginmodules as $module) {
		ob_start();
		$module->login_html(qa_opt('site_url').qa_get('to'), 'register');
		$html=ob_get_clean();
		
		if (strlen($html))
			@$qa_content['custom'].='<BR>'.$html.'<BR>';
	}

	$qa_content['focusid']=isset($errors['handle']) ? 'handle'
		: (isset($errors['password']) ? 'password'
			: (isset($errors['email']) ? 'email' : 'handle'));

			
	return $qa_content;
	

/*
	Omit PHP closing tag to help avoid accidental output
*/