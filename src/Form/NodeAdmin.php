<?php

namespace Drupal\gate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class NodeAdmin extends FormBase {


	public function buildForm(array $form, FormStateInterface $form_state, $node = null) {

		
		$gates = $this->getGates();
		$default = $this->getDefault($node);
		
		$form['Message'] = array(
			'#type' => 'item',
			'#markup' => 'Current Status: ' . (($default == 0)?"Open":"Gated"),
		);

		$form['nid'] = array(
			'#type' => 'hidden',
			'#value' => $node,
		);

		$form['gate'] = array(
			'#type' => 'select',
			'#default_value' => $default,
			'#options' => $gates,
			'#title' => 'Gates',
		);

		$form['submit'] = array(
			'#type' => 'submit',
			'#value' => 'Save',
		);


		
		return $form;
	}

	/**
	 *Return the Form ID
	 **/
	public function getFormId() {
		return 'gate_node_admin_form';
	}


	/**
	 *Submit handler
	 **/
	public function submitForm(array &$form, FormStateInterface $form_state) {
		$gid = $form_state->getValue('gate');
		$nid = $form_state->getValue('nid');

		if($gid == 0) {
			$this->removeNodeFromGates($nid);
		} else {
			$this->addNodeToGate($nid,$gid);
		}
		drupal_flush_all_caches();
		drupal_set_message("Updated!");
	}

	/**
	 * Get a list of all available gates
	 */
	private function getGates() {
		$results = \Drupal::database()->select('gate_gates','g')
			->fields('g',['gid','name'])
			->condition('g.status',1,'=')->execute();
		$gates = array(0=>'NONE');
		foreach($results as $result) {
			$gates[$result->gid] = $result->name;
		}

		return $gates;
	}

	/**
	 * Get current gate, if applicable
	 */
	private function getDefault($nid) {
		$result = \Drupal::database()->select('gate_links','g')
			->fields('g',['gid'])
			->condition('g.link_type','node','=')
			->condition('g.value',$nid,'=')->execute()->fetchField();
		if($result)
			return $result;
		return 0;

	}

	/**
	 * Remove node from all gates
	 */
	private function removeNodeFromGates($nid) {
		\Drupal::database()->delete('gate_links')
			->condition('value',$nid,'=')
			->condition('link_type','node','=')->execute();
	}

	/**
	 * Add node to selected gate, if not already added
	 */
	private function addNodeToGate($nid,$gid) {
		
		if(!$this->nodeExistsOnGate($nid,$gid)) {
			\Drupal::database()->insert('gate_links')
				->fields(array(
					'gid' => $gid,
					'link_type' => 'node',
					'value' => $nid,
					'created' => time(),
					'status' => 1,
				))->execute();
		}
	}

	/**
	 * Check if node exists on gate, return TRUE or FALSE
	 */
	private function nodeExistsOnGate($nid, $gid) {
		$result = \Drupal::database()->select('gate_links','g')
			->fields('g',['gid'])
			->condition('g.gid',$gid,'=')->condition('g.value',$nid,'=')
			->condition('g.link_type','node','=')->execute()->fetchField();
		if($result)
			return TRUE;
		return FALSE;
	}

}
