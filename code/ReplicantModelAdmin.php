<?php
/**
 * UI For Replicant based on ModelAdmin
 */
class ReplicantModelAdmin extends ModelAdmin
{
	private static $url_segment = 'replicantadmin';
	private static $menu_title = 'Replicant';
	private static $menu_icon = 'replicant/images/replicant.png';

	private static $managed_models = array(
		'ReplicantActionDump',
		'ReplicantActionFetch',
		'ReplicantActionRestore',
		'ReplicantActionListFiles',
		'ReplicantActionReadFile'
	);

	public function getEditForm($id = null, $fields = null)
	{
		$form = parent::getEditForm();
		$gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));
		$gridFieldConfig = $gridField->getConfig();

		// we can't edit, but can view
		$gridFieldConfig->removeComponentsByType('GridFieldEditButton');
		$gridFieldConfig->addComponent(new GridFieldViewButton());

		$gridFieldConfig->removeComponentsByType('GridFieldAddNewButton');

		if (singleton($gridField->getModelClass())->canCreate()) {
			$gridFieldConfig->addComponent(new ReplicantAddNewButton());
		}
		return $form;
	}
}

