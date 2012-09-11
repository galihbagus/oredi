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

        
//	Get current information on user

	require_once QA_INCLUDE_DIR.'qa-db-selects.php';
        $userfields=qa_db_select_with_pending(
		qa_db_userfields_selectspec()
	);
        
//	Process submitted form

	if (qa_clicked('doregister')) {
		require_once QA_INCLUDE_DIR.'qa-app-limits.php';
		
		if (qa_limits_remaining(null, QA_LIMIT_REGISTRATIONS)) {
			require_once QA_INCLUDE_DIR.'qa-app-users-edit.php';
			
			$inemail=qa_post_text('email');
			$inpassword=qa_post_text('password');
			$inhandle=qa_post_text('handle');
			$indegree=qa_post_text('degree');
			$inskill=qa_post_text('skill');
			$incrop=qa_post_text('crop');
                        $filename = null;
                        
			$errors=array_merge(
				qa_handle_email_filter($inhandle, $inemail),
				qa_password_validate($inpassword)
			);
			if (is_array(@$_FILES['CV']) && $_FILES['CV']['size']) {
                            if($_FILES['CV']['type'] != "application/pdf") $errors['CV'] = 'Resume must be in PDF format';
                            else {
                                $filename = $inhandle . '-' .$_FILES['CV']['name'];
                                move_uploaded_file($_FILES['CV']['tmp_name'], 'qa-cv-upload/' . $filename);
                            }
                        }
			if (qa_opt('captcha_on_register'))
				qa_captcha_validate_post($errors);
                        
			if (empty($errors)) { // register and redirect
                                
				qa_limits_increment(null, QA_LIMIT_REGISTRATIONS);
				$userid=qa_create_new_user($inemail, $inpassword, $inhandle,20);
                                
                                $inprofile=array();
                                foreach ($userfields as $userfield)
                                        $inprofile[$userfield['fieldid']]=qa_post_text('field_'.$userfield['fieldid']);
                                
                                foreach ($userfields as $userfield)
                                    if (!isset($errors[$userfield['fieldid']]))
                                            qa_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
				
                                qa_db_user_create_expert($userid, $indegree, $inskill,$incrop,$filename);
                                qa_set_logged_in_user($userid, $inhandle);
                                
				$topath=qa_get('to');
				
				if (isset($topath))
					qa_redirect_raw(qa_path_to_root().$topath); // path already provided as URL fragment
				else
					qa_redirect('');
			}
			
		} else
			$pageerror=qa_lang('users/register_limit');
	}


//	Prepare content for theme

	$qa_content=qa_content_prepare();

	$qa_content['title']="Register as a new expert";
	
	$qa_content['error']=@$pageerror;

	$custom=qa_opt('show_custom_register') ? trim(qa_opt('custom_register')) : '';
	
        

        
        
	$qa_content['form']=array(
		'tags' => 'ENCTYPE="multipart/form-data" METHOD="POST" ACTION="'.qa_self_html().'"',
		
		'style' => 'wide',
		
		'fields' => array(
			'custom' => array(
				'type' => 'custom',
				'note' => $custom,
			),
			
			'handle' => array(
				'label' => qa_lang_html('users/handle_label'),
				'tags' => 'NAME="handle" ID="handle"',
				'value' => qa_html(@$inhandle),
				'error' => qa_html(@$errors['handle']),
			),
			
			'password' => array(
				'type' => 'password',
				'label' => qa_lang_html('users/password_label'),
				'tags' => 'NAME="password" ID="password"',
				'value' => qa_html(@$inpassword),
				'error' => qa_html(@$errors['password']),
			),

			'email' => array(
				'label' => qa_lang_html('users/email_label'),
				'tags' => 'NAME="email" ID="email"',
				'value' => qa_html(@$inemail),
				'note' => '<br>'.qa_opt('email_privacy'),
				'error' => qa_html(@$errors['email']),
			),
		),
		
		'buttons' => array(
			'register' => array(
				'label' => qa_lang_html('users/register_button'),
			),
		),
		
		'hidden' => array(
			'doregister' => '1',
		),
	);
	
 //	Other profile fields
        
	foreach ($userfields as $userfield) {
			
            
                $label=trim(qa_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label.=':';
            
		$qa_content['form']['fields'][$userfield['title']]=array(
			'label' => qa_html($label),
			'tags' => 'NAME="field_'.$userfield['fieldid'].'"',
			'error' => qa_html(@$errors[$userfield['fieldid']]),
			'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
		);
	}
        
//	Avatar upload stuff

	if (qa_opt('avatar_allow_gravatar') || qa_opt('avatar_allow_upload')) {
		$avataroptions=array();
		
		if (qa_opt('avatar_default_show') && strlen(qa_opt('avatar_default_blobid'))) {
			$avataroptions['']='<SPAN STYLE="margin:2px 0; display:inline-block;">'.
				qa_get_avatar_blob_html(qa_opt('avatar_default_blobid'), qa_opt('avatar_default_width'), qa_opt('avatar_default_height'), 32).
				'</SPAN> '.qa_lang_html('users/avatar_default');
		} else
			$avataroptions['']=qa_lang_html('users/avatar_none');

		$avatarvalue=$avataroptions[''];
	
		if (qa_opt('avatar_allow_gravatar')) {
			$avataroptions['gravatar']='<SPAN STYLE="margin:2px 0; display:inline-block;">'.
				qa_get_gravatar_html($useraccount['email'], 32).' '.strtr(qa_lang_html('users/avatar_gravatar'), array(
					'^1' => '<A HREF="http://www.gravatar.com/" TARGET="_blank">',
					'^2' => '</A>',
				)).'</SPAN>';

			if (QA_USER_FLAGS_SHOW_GRAVATAR)
				$avatarvalue=$avataroptions['gravatar'];
		}

		if (qa_opt('avatar_allow_upload')) {
			$avataroptions['uploaded']='<INPUT NAME="file" TYPE="file">';
		}
		
		$qa_content['form']['fields']['avatar']=array(
			'type' => 'select-radio',
			'label' => qa_lang_html('users/avatar_label'),
			'tags' => 'NAME="avatar"',
			'options' => $avataroptions,
			'value' => $avatarvalue,
			'error' => qa_html(@$errors['avatar']),
		);
		
	} else
		unset($qa_content['form_profile']['fields']['avatar']);
        
        
        $qa_content['form']['fields']['degree']=array(
                'label'=>'Degree:',
                'type' => 'select',
                'tags' => 'NAME="degree" ID="degree"',
                'value' => 'Agricultural scientist',
                'options' => array ('Student' => 'Student' ,
                                    'Bachelor' => 'Bachelor',
                                    'Masters' => 'Masters',
                                    'PhD' => 'PhD',
                                    'Other' => 'Other',
                                    ), 
        );
        
        $qa_content['form']['fields']['skills']=array(
                'label'=> 'Institution:',
                'type' => 'text',
                'tags' => 'NAME="skill" ID="skill"',
                'value' => '',
        );
        
        $qa_content['form']['fields']['crop']=array(
                'label'=> 'Crop:',
                'type' => 'text',
                'tags' => 'NAME="crop" ID="crop"',
                'value' => ''
            
        );
        $qa_content['form']['fields']['CV']=array(
			'type' => 'file',
			'label' => 'Resume (optional) :',
			'tags' => 'NAME="CV" ID="CV"',
                        'error' => qa_html(@$errors['CV'])
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