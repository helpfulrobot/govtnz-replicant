<?php
/**
 * Class which provides an add button for a replicant action and performs any remote actions.
 */

class ReplicantAddNewButton extends GridFieldAddNewButton implements GridField_URLHandler
{
	private static $allowed_actions = array(
		'handleAdd'
	);

	/**
	 * Handles adding a new instance of the grid's model class.
	 *
	 * @param GridField $grid
	 * @param SS_HTTPRequest $request
	 * @throws Exception
	 * @return \GridFieldAddNewMultiClassHandler
	 */
	public function handleAdd($grid, $request)
	{
		$component = $grid->getConfig()->getComponentByType('GridFieldDetailForm');

		if (!$component) {
			throw new Exception('The ReplicantAddNewButton component requires a detail form component in the grid.');
		}

		$controller = $grid->getForm()->getController();
		$className = $grid->getModelClass();
		$obj = $className::create();

		$handler = new ReplicantAddNewButtonHandler($grid, $component, $obj, $controller);
		$handler->setTemplate($component->getTemplate());
		return $handler;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getHTMLFragments($grid)
	{
		$data = new ArrayData(array(
			'Link' => Controller::join_links($grid->Link(), 'add'),
			'Title' => singleton($grid->getModelClass())->i18n_singular_name()

		));
		return array(
			$this->targetFragment => $data->renderWith(get_class($this))
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getURLHandlers($grid)
	{
		return array(
			'add' => 'handleAdd'
		);
	}
}


/**
 * A custom grid field request handler that allows interacting with form fields when adding records.
 */
class ReplicantAddNewButtonHandler extends GridFieldDetailForm_ItemRequest
{
	/**
	 * Default action to 'add'.
	 * @param GridField $gridField
	 * @param GridField_URLHandler $component
	 * @param DataObject $record
	 * @param Controller $controller
	 */
	public function __construct(GridField $gridField, $component, $record, $controller)
	{
		parent::__construct($gridField, $component, $record, $controller, 'add');
	}

	/**
	 * Save record as normal, then if the record was new invoke the record class (which should be derived from ReplicantAction) to perform the action.
	 *
	 * If the remote address is provided
	 *
	 * @param $data
	 * @param $form
	 * @return HTMLText|SS_HTTPResponse|ViewableData_Customised|void
	 */
	public function doSave($data, $form)
	{
		$list = $this->gridField->getList();
		$controller = Controller::curr();
		$new_record = (!$this->record->isInDB());

		$ok = false;

		try {
			$this->record->update($data);
//            $form->saveInto($this->record);
			$this->record->write();
			// now actually run the action. If it is a dump action and the remote host is not localhost then we call dump on the remote host instead.

			if (($this->record->ClassName != 'ReplicantActionDump')
				|| ($this->record->RemoteHost == 'localhost')) {

				$ok = $this->record->execute();
			} else {
				$transport = Replicant::transportFactory($this->record->Protocol, $this->record->RemoteHost, $this->record->Proxy, $this->record->UserName, $this->record->Password);

				$path = "replicant/dump" . ($this->record->UseGZIP ? "&UseGZIP=1" : "");

				$url = $transport->buildURL($path);

				$this->record->step("Dumping on remote system $url");

				try {
					$result = $transport->fetchPage($path);
				} catch (Exception $e) {
					$result = $e->getMessage();
				}

				// TODO SW better result checking here
				$ok = (false !== strpos($result, 'Success'));
				if ($ok) {
					$this->record->success("Dumped Database on $url: $result");
				} else {
					$this->record->failed("Failed calling $url: $result");
				}
			}

			if ($ok) {
				$link = '"' . $this->record->Title . '"';
				$message = _t(
					'GridFieldDetailForm.Saved',
					'Saved {name} {link}',
					array(
						'name' => $this->record->i18n_singular_name(),
						'link' => $link
					)
				);
				$form->sessionMessage($message, 'good');
			} else {
				$message = _t(
					'Error',
					'Failed to {message}: {error}',
					array(
						'message' => $this->record->i18n_singular_name(),
						'error' => $this->record->ResultInfo
					)
				);
				$form->sessionMessage($message, 'bad');
			}
			$list->add($this->record, null);

		} catch (ValidationException $e) {
			$this->record->failed($e->getResult()->message());
			$form->sessionMessage($e->getResult()->message(), 'bad');
			$responseNegotiator = new PjaxResponseNegotiator(array(
				'CurrentForm' => function () use (&$form) {
						return $form->forTemplate();
					},
				'default' => function () use (&$controller) {
						return $controller->redirectBack();
					}
			));
			if ($controller->getRequest()->isAjax()) {
				$controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
			}
			return $responseNegotiator->respond($controller->getRequest());
		} catch (Exception $e) {
			$this->record->failed($e->getMessage());
			$form->sessionMessage($e->getMessage(), 'bad');
			$responseNegotiator = new PjaxResponseNegotiator(array(
				'CurrentForm' => function () use (&$form) {
						return $form->forTemplate();
					},
				'default' => function () use (&$controller) {
						return $controller->redirectBack();
					}
			));
			if ($controller->getRequest()->isAjax()) {
				$controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
			}
			return $responseNegotiator->respond($controller->getRequest());
		}
		$noActionURL = $controller->removeAction($data['url']);
		$controller->getRequest()->addHeader('X-Pjax', 'Content');
		return $controller->redirect($noActionURL, 302);
	}

	public function Link($action = null)
	{
		if ($this->record->ID) {
			return parent::Link($action);
		} else {
			return Controller::join_links(
				$this->gridField->Link(), 'add'
			);
		}
	}

}
