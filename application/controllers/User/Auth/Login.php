<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends No_Auth_Controller {
	public function __construct() {
		parent::__construct();

		$this->load->library('form_validation');

		$this->load->model('Auth_Model', 'Auth');
	}

	public function index() : void {
		$this->header_data['title'] = 'Login';
		$this->header_data['page']  = 'login';

		$this->form_validation->set_rules('identity', 'Identity',  'required|max_length[254]', array(
			'required'   => 'Please enter your username or email.',
			'max_length' => 'Invalid username.'

		));
		$this->form_validation->set_rules('password', 'Password',  'required|max_length[64]', array(
			'required'   => 'Please enter your password.',
			'max_length' => 'Invalid password.'
		));

		if ($isValid = ($this->form_validation->run() === TRUE)) {
			//form is valid

			//check if identity is email, if not then attempt to use grab from DB
			$identity = $this->Auth->getEmailFromIdentity($this->input->post('identity'));

			$remember = (bool) $this->input->post('remember');
			if($remember) {
				$expire_time = $this->User->set_user_expire_time($this->input->post('remember_time'));
				if($expire_time > 0) {
					$this->input->set_cookie('remember_time', $this->input->post('remember_time'), $expire_time);
				}
			}

			if($identity) {
				if($this->ion_auth->login($identity, $this->input->post('password'), $remember)) {
					//login is successful
					$this->session->set_flashdata('notices', 'Login Successful');

					//redirect to main page, or previous URL
					$this->session->keep_flashdata('referred_from');
					if($prevURL = $this->session->flashdata('referred_from')) {
						redirect($prevURL);
					} else { //@codeCoverageIgnore
						redirect('/user/dashboard', 'refresh');
					} //@codeCoverageIgnore
				} else {
					//login was unsuccessful
					$this->ion_auth->set_error('login_unsuccessful');

					$isValid = FALSE;
				}
			} else {
				//identity doesn't exist
				$this->ion_auth->set_error('login_unsuccessful');

				$isValid = FALSE;
			}
		}

		//login wasn't valid, failed, or this is a fresh login attempt
		if(!$isValid) {
			$this->body_data['form_create'] = array (
				'action' => 'user/login',

				'role'   => 'form'
			);
			$this->body_data['form_identity'] = array(
				'name'        => 'identity',
				'id'          => 'identity',
				'type'        => 'text',

				'class'       => 'form-control input-lg',

				'placeholder' => 'Username or Email Address',
				'value'       => $this->form_validation->set_value('identity'),

				'required'    => ''
			);
			$this->body_data['form_password'] = array(
				'name'        => 'password',
				'id'          => 'password',
				'type'        => 'password',

				'class'       => 'form-control input-lg',

				'placeholder' => 'Password',

				'required'    => '',
			);
			$this->body_data['form_remember'] = array(
				'name'    => 'remember',
				'id'      => 'remember',
				'type'    => 'checkbox',

				'hidden'  => TRUE,

				'checked' => 'checked',
				'value'   => 'remember' //CI is stupid, so we need to pass a value so CI can see it's checked :\
			);
			$this->body_data['form_remember_time'] = array(
				'name'    => 'remember_time',
				'id'      => 'remember_time',

				'class'   => 'form-control form-control-inline',
				'style'   => 'vertical-align: middle',

				'title'   => 'Session timeout'
			);
			$this->body_data['form_remember_time_data'] = array(
				'1day'   => 'for 24 hours',
				'3day'   => 'for 3 Days',
				'1week'  => 'for 1 Week',
				'1month' => 'for 1 Month',
				'3month' => 'for 3 Months'
			);
			$this->body_data['form_submit'] = array(
				'name' => 'submit',
				'type' => 'submit',

				'class' => 'btn btn-lg btn-success btn-block',

				'value' => 'Login'
			);

			$this->session->keep_flashdata('referred_from');
			$this->_render_page('User/Login');
		}
	}
}
