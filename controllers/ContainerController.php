<?php

namespace humhub\modules\custom_pages\controllers;

use Yii;
use yii\web\HttpException;
use humhub\modules\content\components\ContentContainerController;
use humhub\modules\custom_pages\models\ContainerPage;
use humhub\modules\custom_pages\models\AddPageForm;
use humhub\modules\custom_pages\models\CurrentUserGroup;

/**
 * Custom Pages for ContentContainer
 *
 * @author luke
 */
class ContainerController extends ContentContainerController
{

    public $hideSidebar = true;

    public function actionView()
    {
        $page = ContainerPage::find()->contentContainer($this->contentContainer)->where(['custom_pages_container_page.id' => Yii::$app->request->get('id')])->one();

        if ($page === null) {
            throw new HttpException('404', 'Could not find requested page');
        }

        if ($page->type == ContainerPage::TYPE_IFRAME) {
            return $this->render('view_iframe', array('url' => $page->page_content));
        } elseif ($page->type == ContainerPage::TYPE_LINK) {
            return $this->redirect($page->page_content);
        } elseif ($page->type == ContainerPage::TYPE_MARKDOWN) {
            return $this->render('view_markdown', array('md' => $page->page_content));
        } else {
            throw new HttpException('500', 'Invalid page type!');
        }
    }

    public function actionAdd()
    {
        $this->adminOnly();

        $model = new AddPageForm;
        $model->availableTypes = ContainerPage::getPageTypes();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            return $this->redirect($this->contentContainer->createUrl('edit', ['type' => $model->type]));
        }

        return $this->render('add', ['model' => $model]);
    }

    public function actionList()
    {
        $this->adminOnly();

        $pages = ContainerPage::find()->contentContainer($this->contentContainer)->all();
        
        return $this->render('list', array('pages' => $pages, 'container' => $this->contentContainer));
    }

    public function actionEdit()
    {
        $this->adminOnly();

        $page = ContainerPage::find()->contentContainer($this->contentContainer)->where(['custom_pages_container_page.id' => Yii::$app->request->get('id')])->one();

        if ($page === null) {
            $page = new ContainerPage;
            $page->content->container = $this->contentContainer;
            $page->type = (int) Yii::$app->request->get('type');
        }
        $page->content->visibility = \humhub\modules\content\models\Content::VISIBILITY_PUBLIC;

        if ($page->load(Yii::$app->request->post()) && $page->validate() && $page->save()) {
            \humhub\modules\file\models\File::attachPrecreated($page, Yii::$app->request->post('fileUploaderHiddenGuidField'));
            return $this->redirect($this->contentContainer->createUrl('list'));
        }

        return $this->render('edit', array('page' => $page));
    }

    public function actionDelete()
    {
        $this->adminOnly();

        $page = ContainerPage::find()->contentContainer($this->contentContainer)->where(['custom_pages_container_page.id' => Yii::$app->request->get('id')])->one();


        if ($page !== null) {
            $page->delete();
        }

        return $this->redirect($this->contentContainer->createUrl('list'));
    }

    protected function adminOnly()
    {
        if (!$this->contentContainer->isAdmin()) {
            throw new \yii\web\HttpException('403', 'Access denied!');
        }

    }

    protected function groupCheck()
    {
        $usergroupid = CurrentUserGroup::find();
        $page = ContainerPage::findOne(['custom_pages_container_page.id' => Yii::$app->request->get('id')]);
        // Turn on output buffering

        if ($page === null) {
            throw new HttpException('404', 'Could not find requested page');
        }

        $allowedgroups = $page->groups_allowed;

        $groups = explode(",",$allowedgroups);

        if($this->contentContainer->isAdmin()){
            return true;
        }else if(!in_array($usergroupid,$groups)) {
            return false;
        }
        return true;
    }

}
