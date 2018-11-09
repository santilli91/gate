<?php

namespace Drupal\gate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GateAdminLanding extends FormBase {
	public function buildForm(array $form, FormStateInterface $form_state, $gid = null) {
		$form['tmp_1'] = array(
			'#type' => 'item',
			'#markup' => 'Gating Admin: Contains listing of all gates<br><br><a href="/admin/config/gate/new">New Gate</a><br><table><tbody><tr><th>Name</th><th>Enabled</th></tr>',
		);

		foreach($this->getAllGates() as $gate) {
			$form['gates'][$gate->gid] = array(
				'#type' => 'checkbox',
				'#prefix' => '<tr><td><a href="/admin/config/gate/' . $gate->gid . '">' . $gate->name . '</a></td><td>',
				'#suffix' => '</td></tr>',
				'#default_value' => $gate->status,
			);
		}

		$form['tmp_2'] = array(
			'#type' => 'item',
			'#markup' => '</tbody></table>',
		);



		return $form;
	}

	public function getFormId() {
		return 'gate_admin_landing_form';
	}


	public function submitForm(array &$form, FormStateInterface $form_state) {
	}



	private function getAllGates($status = 1, $page = null, $offset = null) {
		$query = \Drupal::database()->select('gate_gates','g');
		$query->fields('g',[]);
		$query->orderBy('g.status','DESC');
		$result = $query->execute()->fetchAll();
		return $result;
	}


}
