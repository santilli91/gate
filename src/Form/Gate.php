<?php

namespace Drupal\gate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Gate extends FormBase {

	private $details = array();
	//Build the base form, reach out for the rest of the form fields
	public function buildForm(array $form, FormStateInterface $form_state) {
		
		$form['detail']['title'] = array(
			'#type' => 'item',
			'#markup' => '<h2>' . $this->details['name'] . '</h2>',
		);
		$form['detail']['text'] = array(
			'#type' => 'item',
			'#markup' => 'You need to login to view this page',
		);
		$form['login_fields'] = $this->getFormDetails();


		$form['gid'] = array(
			'#type' => 'hidden',
			'#value' => $this->details['gid'],
		);

		$form['submit'] = array(
			'#type' => 'submit',
			'#value' => 'Authenticate',
		);
		return $form;
	}

	function __construct($details) {
		$this->details = $details;
	}

	//Generate teh form id
	public function getFormId() {
		return 'Authentication Form';
	}


	//Process the form once it has been submitted
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$gate = getGateByID($form_state->getValue('gid'));
		switch($gate['gate_type']) {
			case 'simple':
				$authenticated = $this->authenticateSimple($gate['gid'],$form_state->getValue('password'));
				break;
			case 'unauthenticated':
				$authenticated = $this->authenticateComplex($gate['gid'],$form_state->getValue('username'),$form_state->getValue('password'));
		}

		if($authenticated) {
			setcookie('gate:' . $gate['gid'], 1, time()+36000,'/');
			drupal_set_message('Authenticated');
		} else {
			drupal_set_message('Invalid Login','warning');
		}
	}


	//Get the form type, then reach out with the type to the correct function to generate the form
	private function getFormDetails() {
		$form = '';
		switch($this->getGateType($this->details['gid'])) {
			case 'simple':
				$form = $this->getSimpleLoginForm();
				break;
			case 'unauthenticated':
				$form = $this->getUnAuthenticatedLoginForm();
				break;
		}

		return $form;
		
	}



	//Get the simple login form
	private function getSimpleLoginForm() {

		$form['password'] = array(
			'#type' => 'password',
			'#title' => 'Password',
		);
		return $form;
	}

	//Get the UnAuthenticated login form, (username and password)
	private function getUnAuthenticatedLoginForm() {
		$form['username'] = array(
			'#type' => 'textfield',
			'#title' => 'Username',
		);

		$form['password'] = array(
			'#type' => 'password',
			'#title' => 'Password',
		);

		return $form;
	}

	//Query the db for the Gate type with the provided gate id
	private function getGateType($gid) {
		$query = \Drupal::database()->select('gate_gates', 'g');
		$query->fields('g',['gate_type']);
		$query->condition('g.gid',$gid,'=');
		$type = $query->execute()->fetchField();
		return $type;
	}

	//Authenticate a simple user
	private function authenticateSimple($gid, $password) {
		$query = \Drupal::database()->select('gate_simple_login_info','g');
		$query->fields('g',['gid']);
		$query->condition('g.gid',$gid,'=');
		$query->condition('g.password',$password,'=');
		$result = $query->execute()->fetchField();
		if($result)
			return true;
		return false;
	}

	//Authenticate using username and password
	private function authenticateComplex($gid, $username, $password) {
		$query = \Drupal::database()->select('gate_unauthenticated_login_info','g');
		$query->fields('g',['gid']);
		$query->condition('g.gid',$gid,'=');
		$query->condition('g.password',$password,'=');
		$query->condition('g.username',$username,'=');
		$result = $query->execute()->fetchField();
		return $result;
	}
}
