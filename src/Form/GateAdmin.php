<?php

namespace Drupal\gate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GateAdmin extends FormBase {
	public function buildForm(array $form, FormStateInterface $form_state, $gid = null) {


		
		$form['tmp'] = array(
			'#type' => 'item',
			'#markup' => 'Gate Aministration:',
		);


		//Get basic information about the gate and display a form for editing
		$gate = $this->getGate($gid);

		$form['info'] = array(
			'#type' => 'details',
			'#title' => 'Info',
			'#collapsible' => TRUE,
			'#open' => TRUE,
		);
		$form['info']['title_info'] = array(
			'#type' => 'item',
			'#markup' => '<h2>' . $gate[0]->name . '</h2>',
		);

		$form['info']['name'] = array(
			'#type' => 'textfield',
			'#default_value' => $gate[0]->name,
			'#title' => 'Name: ',
		);

		$form['info']['gate_type'] = array(
			'#type' => 'select',
			'#default_value' => $gate[0]->gate_type,
			'#options' => array(
				'unauthenticated' => 'unauthenticated',
				'simple' => 'simple',
				'drupal' => 'drupal',
			),
		);

		$form['info']['status'] = array(
			'#type' => 'checkbox',
			'#title' => 'Enabled',
			'#default_value' => $gate[0]->status,
		);

		$form['info']['submit_info'] = array(
			'#type' => 'submit',
			'#value' => 'Save Info',
			'#name' => 'submit_info',
		);

		//Create hidden field for use later (the gate id)
		$form['gid'] = array(
			'#type' => 'hidden',
			'#value' => $gid,
		);

		if($gid != 'new') {


			//Fieldset for editing the links for the gate, as well as the login information
			$form['login_settings'] = array(
				'#type' => 'details',
				'#title' => 'Login Settings',
			);


			//Fieldset and information on the links that are attached to the gate
			$form['login_settings']['links'] = array(
				'#type' => 'details',
				'#title' => 'Links',
			);

			$form['login_settings']['links']['submit'] = array(
				'#type' => 'submit',
				'#value' => 'Save Links',
				'#name' => 'submit_links',
			);

			$form['login_settings']['links']['list'] = $this->getFormatedLinks($gid);

			//Fieldset and information on the login information that is attached to the gate
			$form['login_settings']['login_info'] = array(
				'#type' => 'details',
				'#title' => 'Login Info',
			);

			$form['login_settings']['login_info']['submit'] = array(
				'#type' => 'submit',
				'#value' => 'Save Login Info',
				'#name' => 'submit_logins',
			);

			$form['login_settings']['login_info']['list'] = $this->getLoginSettings($gid,$gate[0]->gate_type);
		}
		return $form;
	}

	/**
	 *Return the Form ID
	 **/
	public function getFormId() {
		return 'gate_admin_form';
	}


	/**
	 *Submit handler
	 **/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$submit = $form_state->getTriggeringElement();


		if($form_state->getValue('gid') == 'new') {
			$this->createNewGate($form_state);
		}
		else {

			if($submit['#name'] == 'submit_info') 
				$this->updateInfo($form_state);
			else if($submit['#name'] == 'submit_links')
				$this->updateLinks($form_state);
			else if($submit['#name'] == 'submit_logins')
				$this->updateLoginInfo($form_state);
		}
		drupal_flush_all_caches();
	}

	/**
	 *Get basic gate information given the gate ID
	 **/
	private function getGate($gid) {
		$query = \Drupal::database()->select('gate_gates','g');
		$query->fields('g',[]);
		$query->condition('g.gid',$gid,'=');
		$result = $query->execute()->fetchAll();
		return $result;
	}

	/**
	 *Get a form formatted array of the links attached to the form
	 **/
	private function getFormatedLinks($gid) {
		$links = $this->getLinks($gid);

		$form['table_start'] = array(
			'#type' => 'item',
			'#markup' => '<table><tbody><tr><th>Type</th><th>Path/Node Title</th><th>Delete</th></tr>',
		);
		$form['add_link_uri'] = array(
			'#type' => 'textfield',
			'#prefix' => '<tr><td>New URI Link</td><td>',
			'#suffix' => '</td><td></td></tr>',
		);
		$form['add_link_node'] = array(
			'#type' => 'entity_autocomplete',
  			'#target_type' => 'node',
			'#prefix' => '<tr><td>New Node Link</td><td>',
			'#suffix' => '</td><td></td></tr>',
		);
		$form['add_link_taxonomy'] = array(
			'#type' => 'entity_autocomplete',
			'#target_type' => 'taxonomy_term',
			'#prefix' => '<tr><td>Gate All Content With Term:</td><td>',
			'#suffix' => '</td><td></td></tr>',
		);
		$form['gate_all'] = array(
			'#type' => 'number',
			'#prefix' => '<tr><td>Gate All Content After <u>&nbsp;x&nbsp;</u> Page Views</td><td>',
			'#suffix' => '</td><td></td></tr>',
		);
		foreach($links as $link) {
			$value = '<a href="' . $link->value . '" target="_blank">' . $link->value . '</a>';
			if($link->link_type == 'node')
				$value = '<a href="/node/' . $link->value  . '" target="_blank">' . $this->getNodeTitle($link->value) . '</a>';
			if($link->link_type == 'entity_term')
				$value = $this->getTaxonomyTitle($link->value);
			if($link->link_type == 'all')
				$value = $link->value . ' page views before gating';
			$form[$link->lid]['info'] = array(
				'#type' => 'item',
				'#markup' => '<tr><td>' . $link->link_type . '</td><td>' . $value . '</td>',
			);
			$form[$link->lid]['disable-' . $link->lid] = array(
				'#type' => 'checkbox',
				'#prefix' => '<td>',
				'#suffix' => '</td></tr>',
			);
		}

		$form['table_end'] = array(
			'#type' => 'item',
			'#markup' => '</tbody></table>',
		);
		return $form;
	}

	/**
	 *Get all of the links attached to the gate, ordered by status, then type
	 **/
	private function getLinks($gid) {
		$query = \Drupal::database()->select('gate_links','l');
		$query->fields('l',[]);
		$query->condition('l.gid',$gid,'=');
		$query->orderBy('l.link_type','ASC');
		$results = $query->execute()->fetchAll();
		return $results;
	}

	/**
	 *Get the admin login settings, depending on gate type
	 **/
	private function getLoginSettings($gid,$gate_type) {
		$form = array();
		switch($gate_type) {
			case 'simple':
				$form = $this->getSimpleLoginSettingFormatted($gid);
				break;
			case 'unauthenticated':
				$form = $this->getComplexLoginSettingFormatted($gid);
				break;
		}

		return $form;

	}

	/**
	 *Get All login information for unathenticated login type
	 **/
	private function getComplexLoginSettingFormatted($gid) {
		$query = \Drupal::database()->select('gate_unauthenticated_login_info','s');
		$query->fields('s',[]);
		$query->condition('s.gid',$gid,'=');
		$results = $query->execute()->fetchAll();

		$form['gate_type_hidden'] = array(
			'#type' => 'hidden',
			'#value' => 'unauthenticated',
		);
		$form['table_start'] = array(
			'#type' => 'item',
			'#markup' => '<table><tbody><tr><th>Username</th><th>Password</th><th>Delete</th></tr>',
		);

		foreach($results as $result) {
			$form[$result->aid]['username-' . $result->aid] = array(
				'#type' => 'textfield',
				'#prefix' => '<tr><td>',
				'#suffix' => '</td>',
				'#default_value' => $result->username,
			);
			$form[$result->aid]['password-' . $result->aid] = array(
				'#type' => 'textfield',
				'#prefix' => '<td>',
				'#suffix' => '</td>',
				'#default_value' => $result->password,
			);
			$form[$result->aid]['delete-' . $result->aid] = array(
				'#type' => 'checkbox',
				'#prefix' => '<td>',
				'#suffix' => '</td></tr>',
			);
		}

		$form['table_end'] = array(
			'#type' => 'item',
			'#markup' => '</tbody></table>',
		);

		return $form;
	}

	/**
	 *Get simple authentication login password and format it
	 **/
	private function getSimpleLoginSettingFormatted($gid) {
		$query = \Drupal::database()->select('gate_simple_login_info','s');
		$query->fields('s',['password']);
		$query->condition('s.gid',$gid,'=');
		$password = $query->execute()->fetchField();

		$form['gate_type_hidden'] = array(
			'#type' => 'hidden',
			'#value' => 'simple',
		);
		$form['password'] = array(
			'#type' => 'textfield',
			'#default_value' => $password,
			'#title' => 'Password',
		);
		return $form;
	}

	/**
	 *Get the title of a node given the NID
	 **/
	private function getNodeTitle($nid) {
		$query = \Drupal::database()->select('node_field_data','n');
		$query->fields('n',['title']);
		$query->condition('n.nid',$nid,'=');
		return $query->execute()->fetchField();
	}

	/**
	 *Get the title of the taxonomy term with provided TID
	 **/
	private function getTaxonomyTitle($tid) {
		return \Drupal::database()->select('taxonomy_term_field_data','t')
			->fields('t',['name'])
			->condition('t.tid',$tid,'=')->execute()->fetchField();
	}

	/**
	 * Save a new gate
	 */
	private function createNewGate(FormStateInterface $form_state) {
		$query = \Drupal::database()->insert('gate_gates')
			->fields(array(
				'status' => $form_state->getValue('status'),
				'name' => $form_state->getValue('name'),
				'gate_type' => $form_state->getValue('gate_type'),
			))->execute();

	}

	/**
	 *Update the basic info of the gate
	 **/
	private function updateInfo(FormStateInterface $form_state) {
		$query = \Drupal::database()->update('gate_gates');
		$query->fields(array(
			'status' => $form_state->getValue('status'),
			'name' => $form_state->getValue('name'),
			'gate_type' => $form_state->getValue('gate_type'),
		));
		$query->condition('gid',$form_state->getValue('gid'),'=');
		$query->execute();

		drupal_set_message('Basic Gate Information Has Been Updated');
	}

	/**
	 *Update the status of the links for the gate
	 **/
	private function updateLinks(FormStateInterface $form_state) {
		$complete_form = $form_state->getCompleteForm();
		foreach($complete_form['login_settings']['links']['list'] as $key=>$link) {
			if(is_numeric($key)) {
				if($form_state->getValue('disable-' . $key) == '1')
					$query = \Drupal::database()->delete('gate_links')
					->condition('lid',$key,'=')->condition('gid',$form_state->getValue('gid'),'=')->execute();
			}
		}
		if($new_link = $complete_form['login_settings']['links']['list']['add_link_uri']['#value']) {
			if($this->checkForExistingLink($new_link,'uri'))
				drupal_set_message('This link is already gated');
			else {
				$query = \Drupal::database()->insert('gate_links')
					->fields(array(
						'status' => 1,
						'gid' => $complete_form['gid']['#value'],
						'link_type' => 'uri',
						'value' => $new_link,
						'created' => time(),
					))->execute();
			}
		}
		if($new_link = $form_state->getValue('add_link_node')) {
			if($this->checkForExistingLink($new_link,'node'))
				drupal_set_message('This link is already gated');
			else {
				$query = \Drupal::database()->insert('gate_links')
					->fields(array(
						'status' => 1,
						'gid' => $complete_form['gid']['#value'],
						'link_type' => 'node',
						'value' => $new_link,
						'created' => time(),
					))->execute();
			}
		}
		if($new_link = $form_state->getValue('add_link_taxonomy')) {
			if($this->checkForExistingLink($new_link,'entity_term')) 
				drupal_set_message('This term has already been gated');
			else {
				$query = \Drupal::database()->insert('gate_links')
					->fields(array(
						'status' => 1,
						'gid' => $complete_form['gid']['#value'],
						'link_type' => 'entity_term',
						'value' => $new_link,
						'created' => time(),
					))->execute();
			}
		}
		if($new_link = $form_state->getValue('gate_all')) {
			if($x = $this->checkForExistingGateAll($new_link,$complete_form['gid']['#value'])) {
				if($x == 1)
					drupal_set_message('Gate qty has been updated');
				else 
					drupal_set_message('There is already a gate gating all content');
			}
			else {
				$query = \Drupal::database()->insert('gate_links')
					->fields(array(
						'status' => 1,
						'gid' => $complete_form['gid']['#value'],
						'link_type' => 'all',
						'value' => $new_link,
						'created' => time(),
					))->execute();
			}
		}
		drupal_set_message('Links have been updated');
	}

	/**
	 *Update the existing login information
	 **/
	private function updateLoginInfo(FormStateInterface $form_state) {
		$complete_form = $form_state->getCompleteForm();

		if($complete_form['login_settings']['login_info']['list']['gate_type_hidden']['#value'] == 'unauthenticated') {
	
			foreach($complete_form['login_settings']['login_info']['list'] as $key=>$link) {
				if(is_numeric($key)) {
					if($link['delete-' . $key]['#value']) {
						$query = \Drupal::database()->delete('gate_unauthenticated_login_info');
						$query->condition('aid',$key,'=')->execute();
					} else {
		
						$query = \Drupal::database()->update('gate_unauthenticated_login_info')
						->fields(array(
							'password' => $link['password-' . $key]['#value'],
							'username' => $link['username-' . $key]['#value'],
						))
						->condition('aid',$key,'=')
						->condition('gid',$form_state->getValue('gid'),'=')
						->execute();
					}
				}
			}
		}
		else if($complete_form['login_settings']['login_info']['list']['gate_type_hidden']['#value'] == 'simple') {
			$query = \Drupal::database()->merge('gate_simple_login_info');
			$query->key(array('gid'=>$complete_form['gid']['#value']));
			$query->fields(array(
				'password' => $complete_form['login_settings']['login_info']['list']['password']['#value'],
				'gid' =>$complete_form['gid']['#value'],
			));
			//$query->condition('gid',$complete_form['gid']['#value'],'=')
			$query->execute();
		}
	drupal_set_message('Login Information has been updated');
	}

	/**
	 *Check for existing link
	 **/
	private function checkForExistingLink($new_link,$type) {
		$link = \Drupal::database()->select('gate_links','g')
				->fields('g',['lid'])
				->condition('g.value',$new_link,'=')
				->condition('g.link_type',$type,'=')->execute()->fetchField();
		return $link;
	}

	/**
	 *Check to make sure no gate is gating by all already
	 **/
	private function checkForExistingGateAll($new_value,$gid) {
		$existing = \Drupal::database()->select('gate_links','l')
			->fields('l',['gid'])
			->condition('l.link_type','all','=')
			->execute()->fetchField();
		if($existing == $gid) {
			$query = \Drupal::database()->update('gate_links')
				->fields(array('value'=>$new_value))->condition('link_type','all','=')->execute();
			return 1;
		}
		else if($existing)
			return 2;
		return null;
	}
}
