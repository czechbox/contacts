<?php
/**
 * @author Thomas Tanghus
 * Copyright (c) 2013 Thomas Tanghus (thomas@tanghus.net)
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OCA\Contacts\Controller;

use OCA\Contacts\App,
	OCA\Contacts\JSONResponse,
	OCA\Contacts\Utils\JSONSerializer,
	OCA\AppFramework\Controller\Controller as BaseController,
	OCA\AppFramework\Http\TextDownloadResponse;


/**
 * Controller class For Address Books
 */
class AddressBookController extends BaseController {

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function userAddressBooks() {
		$app = new App($this->api->getUserId());
		$addressBooks = $app->getAddressBooksForUser();
		$response = array();
		foreach($addressBooks as $addressBook) {
			$response[] = $addressBook->getMetaData();
		}
		$response = new JSONResponse(
			array(
				'addressbooks' => $response,
			));
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function getAddressBook() {
		\OCP\Util::writeLog('contacts', __METHOD__, \OCP\Util::DEBUG);
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$addressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		$lastModified = $addressBook->lastModified();
		$response = new JSONResponse();

		if(!is_null($lastModified)) {
			$response->addHeader('Cache-Control', 'private, must-revalidate');
			$response->setLastModified(\DateTime::createFromFormat('U', $lastModified) ?: null);
			$response->setETag(md5($lastModified));
		}

		if($this->request->method === 'GET') {
			$contacts = array();
			foreach($addressBook->getChildren() as $i => $contact) {
				$result = JSONSerializer::serializeContact($contact);
				//\OCP\Util::writeLog('contacts', __METHOD__.' contact: '.print_r($result, true), \OCP\Util::DEBUG);
				if($result !== null) {
					$contacts[] = $result;
				}
			}
			$response->setParams(array('contacts' => $contacts));
		}
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @CSRFExemption
	 */
	public function exportAddressBook() {
		\OCP\Util::writeLog('contacts', __METHOD__, \OCP\Util::DEBUG);
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$addressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		$lastModified = $addressBook->lastModified();
		$response = new JSONResponse();

		if(!is_null($lastModified)) {
			$response->addHeader('Cache-Control', 'private, must-revalidate');
			$response->setLastModified(\DateTime::createFromFormat('U', $lastModified) ?: null);
			$response->setETag(md5($lastModified));
		}

		$contacts = '';
		foreach($addressBook->getChildren() as $i => $contact) {
			$contacts .= $contact->serialize() . "\r\n";
		}
		$name = str_replace(' ', '_', $addressBook->getDisplayName()) . '.vcf';
		return new TextDownloadResponse($contacts, $name, 'text/directory');
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function addAddressBook() {
		$app = new App($this->api->getUserId());
		$params = $this->request->urlParams;

		$response = new JSONResponse();

		$backend = $app->getBackend($params['backend']);
		if(!$backend->hasAddressBookMethodFor(\OCP\PERMISSION_CREATE)) {
			throw new \Exception('Not implemented');
		}
		$id = $backend->createAddressBook($this->request->post);
		if($id === false) {
			$response->bailOut(App::$l10n->t('Error creating address book'));
			return $response;
		}

		$response->setStatus('201');
		$response->setParams($backend->getAddressBook($id));
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function updateAddressBook() {
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$response = new JSONResponse();

		$addressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		if(!$addressBook->update($this->request['properties'])) {
			$response->bailOut(App::$l10n->t('Error updating address book'));
			return $response;
		}
		$response->setParams($addressBook->getMetaData());
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function deleteAddressBook() {
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$response = new JSONResponse();

		$backend = $app->getBackend($params['backend']);
		// TODO: Check actual permissions
		if(!$backend->hasAddressBookMethodFor(\OCP\PERMISSION_DELETE)) {
			throw new \Exception('Not implemented');
		}
		if(!$backend->deleteAddressBook($params['addressbookid'])) {
			$response->bailOut(App::$l10n->t('Error deleting address book'));
			return $response;
		}
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function addChild() {
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$response = new JSONResponse();

		$addressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		$id = $addressBook->addChild();
		if($id === false) {
			$response->bailOut(App::$l10n->t('Error creating contact.'));
			return $response;
		}
		$contact = $addressBook->getChild($id);
		$response->setStatus('201');
		$response->setETag($contact->getETag());
		$response->addHeader('Location',
			\OCP\Util::linkToRoute(
				'contacts_contact_get',
				array(
					'backend' => $params['backend'],
					'addressbookid' => $params['addressbookid'],
					'contactid' => $id
				)
			)
		);
		$response->setParams(JSONSerializer::serializeContact($contact));
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function deleteChild() {
		$params = $this->request->urlParams;
		$app = new App($this->api->getUserId());

		$response = new JSONResponse();

		$addressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		$result = $addressBook->deleteChild($params['contactid']);
		if($result === false) {
			$response->bailOut(App::$l10n->t('Error deleting contact.'));
		}
		return $response;
	}

	/**
	 * @IsAdminExemption
	 * @IsSubAdminExemption
	 * @Ajax
	 */
	public function moveChild() {
		$params = $this->request->urlParams;
		$targetInfo = $this->request->post['target'];
		$app = new App($this->api->getUserId());

		$response = new JSONResponse();

		// TODO: Check if the backend supports move (is 'local' or 'shared') and use that operation instead.
		// If so, set status 204 and don't return the serialized contact.
		$fromAddressBook = $app->getAddressBook($params['backend'], $params['addressbookid']);
		$targetAddressBook = $app->getAddressBook($targetInfo['backend'], $targetInfo['id']);
		$contact = $fromAddressBook->getChild($params['contactid']);
		if(!$contact) {
			$response->bailOut(App::$l10n->t('Error retrieving contact.'));
			return $response;
		}
		$contactid = $targetAddressBook->addChild($contact);
		$contact = $targetAddressBook->getChild($contactid);
		if(!$contact) {
			$response->bailOut(App::$l10n->t('Error saving contact.'));
			return $response;
		}
		if(!$fromAddressBook->deleteChild($params['contactid'])) {
			// Don't bail out because we have to return the contact
			$response->debug(App::$l10n->t('Error removing contact from other address book.'));
		}
		$response->setParams(JSONSerializer::serializeContact($contact));
		return $response;
	}

}

