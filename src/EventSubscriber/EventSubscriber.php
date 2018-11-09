<?php


/**
This is currently not being used
**/

namespace Drupal\gate\EventSubscriber;

use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class EventSubscriber implements EventSubscriberInterface {

	/**
	 * {@inheritdoc}
	 */
	public static function getSubscribedEvents() {
		//$events[KernelEvents::REQUEST][] = array('checkForGate');
		return $events;
	}


	/**
	 * Check for gating, this is the main function for the gate module
	 */
	public function checkForGate(GetResponseEvent $event) {
		$path = $_SERVER['REQUEST_URI'];
		
		if(
			!(strpos($path,'admin') !== FALSE) &&
			!(strpos($path,'/history/') !== FALSE) &&
			!(strpos($path,'/quickedit/') !== FALSE)
		) {
			if($gid = $this->getGate($path)) {
				drupal_set_message('Gate');
				if($gid['gate_type'] == 'drupal') {
					if(\Drupal::currentUser()->id() == 0)
						$event->setResponse(new TrustedRedirectResponse('/user/login?destination=' . $path));
				}
				else
					$event->setResponse(new TrustedRedirectResponse('/gate/gated?gid='.$gid['gid'].'&location='.$path));
			}
			else
				drupal_set_message('No Gate');
		}
	}



	public function getGate($path) {
		
		$query = \Drupal::database()->select('gate_links', 'l');
		$query->join('gate_gates','g','g.gid=l.gid');
		$query->fields('g', ['gid','gate_type']);
		$query->condition('l.value', $path,'=');
		$query->condition('g.status',1,'=');
		$result = $query->execute()->fetchAssoc();
		return $result;
	}
}
