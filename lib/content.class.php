<?php
  /**
   * Content Class
   *
   * @package Wojo Framework
   * @author wojoscripts.com
   * @copyright 2014
   * @version $Id: content.class.php, v1.00 2012-03-05 10:12:05 gewa Exp $
   */

  if (!defined("_WOJO"))
      die('Direct access to this location is not allowed.');


  class Content
  {

      const cTable = "countries";
	  const blTable = "banlist";
	  const pgTable = "pages";
	  const muTable = "menus";
	  const faqTable = "faq";
	  const ctTable = "categories";
	  const cdTable = "conditions";
	  const fTable = "features";
	  const fuTable = "fuel";
	  const trTable = "transmissions";
	  const mkTable = "makes";
	  const mdTable = "models";
	  const lcTable = "locations";
	  const slTable = "slider";
	  const dcTable = "coupons";
	  const rwTable = "reviews";
	  const nwaTable = "news";
	  const gwTable = "gateways";
	  const msTable = "memberships";
	  const nwTable = "newsletter";
	  const txTable = "payments";
	  const inTable = "invoices";

	  public $cattree = array();
	  public $catlist = array();
	  
	  private static $db;


      /**
       * Content::__construct()
       * 
       * @return
       */
      public function __construct()
      {
          self::$db = Db::run();

      }
	  
      /**
       * Content::getCountryList()
       * 
       * @return
       */
      public function getCountryList()
      {

		  $row = self::$db->select(self::cTable, null, null, "ORDER BY sorting DESC")->results();

          return ($row) ? $row : 0; 

      }
	  
      /**
       * Content::processCountry()
       * 
       * @return
       */
      public function processCountry()
      {
		  $validate = Validator::instance();
		  $validate->addSource($_POST);

		  $validate->addRule('name', 'string', true, 2, 20, Lang::$word->NAME);
		  $validate->addRule('abbr', 'string', true, 2, 2, Lang::$word->CNT_ABBR);
		  $validate->addRule('sorting', 'numeric', false, 1, 1000);
		  $validate->addRule('active', 'numeric', false, 1, 1);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'name' => $validate->safe->name, 
					'abbr' => $validate->safe->abbr, 
					'active' => $validate->safe->active,
					'home' => $_POST['home'],
					'vat' => $_POST['vat'],
					'sorting' => $validate->safe->sorting,
			  );

			  if ($data['home'] == 1) {
				  self::$db->pdoQuery("UPDATE `" . self::cTable . "` SET `home`= DEFAULT(home);");
			  }	
  
              self::$db->update(self::cTable, $data, array('id' => Filter::$id));
			  Message::msgReply(self::$db->affected(), 'success', Lang::$word->CNT_UPDATED);
		  } else {
			  Message::msgSingleStatus();
		  }
	  }
	  
      /**
       * Content::getBanList()
       * 
       * @return
       */
	  public function getBanList()
	  {
		  if (isset($_GET['sort'])) {
			  $sort = Validator::sanitize($_GET['sort'], "alpha", 5);
			  if ($sort == "ip" or $sort == "email") {
				  if ($sort == "ip") {
					  $where = "WHERE type = 'IP'";
				  }
				  if ($sort == "email") {
					  $where = "WHERE type = 'Email'";
				  }
			  } else {
				  $where = null;
			  }
			  Debug::addMessage('params', 'sort', print_r($_GET['sort'], true));
		  } else {
			  $where = null;
		  }
	
		  $sql = "
			  SELECT id, item,
				CASE
				  WHEN type = 'IP' 
				  THEN '" . Lang::$word->IP . "' 
				  WHEN type = 'Email' 
				  THEN '" . Lang::$word->EMAIL . "' 
				  ELSE 'Unknown' 
				END type, comment
			  FROM `" . self::blTable . "` 
			  $where";
		  $row = self::$db->pdoQuery($sql)->results();
	
		  return ($row) ? $row : 0;
	  }
	  
      /**
       * Content::processBan()
       * 
       * @return
       */
      public function processBan()
      {
		  $validate = Validator::instance();
		  $validate->addSource($_POST);

		  $validate->addRule('type', 'string', true, 2, 20, Lang::$word->BL_ITEM);
		  $validate->addRule('item', 'string', true, 4, 100, Lang::$word->BL_TYPE);
		  $validate->addRule('comment', 'string', false);

		  if($_POST['type'] == "IP") {
			  $validate->addRule('item', 'ipv4', true);
		  } else {
			  $validate->addRule('item', 'email', true);
		  }
		  $validate->run();
          if (empty(Message::$msgs)) {
              $data = array(
					'type' => $validate->safe->type, 
					'item' => $validate->safe->item, 
					'comment' => $validate->safe->comment
					
			  );
              self::$db->insert(self::blTable, $data);
			  Message::msgReply(self::$db->getLastInsertId(), 'success', Lang::$word->BL_ADDED);

		  } else {
			  Message::msgSingleStatus();
		  }

	  }

      /**
       * Content::processEmail()
       * 
       * @return
       */
	  public function processEmail()
	  {
	
		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('subject', 'string', true, 3, 150, Lang::$word->EMN_REC_SUBJECT);
		  $validate->addRule('recipient', 'string', true, 3, 150, Lang::$word->EMN_REC_SEL);
		  $validate->addRule('from', 'email');
		  $validate->run();
	
		  if (empty(Message::$msgs)) {
			  $to = Validator::sanitize($_POST['recipient']);
			  $subject = Validator::cleanOut($_POST['subject']);
			  $body = Validator::cleanOut($_POST['body']);
			  $numSent = 0;
			  $failedRecipients = array();
	
			  switch ($to) {
				  case "members":
					  $mailer = Mailer::sendMail();
					  $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));
	
					  $userrow = self::$db->select(Users::mTable, array('email', 'CONCAT(fname," ",lname) as name'), array('active'=>'y'))->results();
	
					  $replacements = array();
					  if ($userrow) {
						  foreach ($userrow as $cols) {
							  $replacements[$cols->email] = array(
								  '[COMPANY]' => App::get("Core")->company,
								  '[LOGO]' => Utility::getLogo(),
								  '[NAME]' => $cols->name,
								  '[SITEURL]' => SITEURL,
								  '[DATE]' => date('Y'));
						  }
	
						  $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
						  $mailer->registerPlugin($decorator);
	
						  $message = Swift_Message::newInstance()
									->setSubject($subject)
									->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
									->setBody($body, 'text/html');
	
						  foreach ($userrow as $row) {
							  $message->setTo(array($row->email => $row->name));
							  $numSent++;
							  $mailer->send($message, $failedRecipients);
						  }
						  unset($row);
					  }
					  break;
	
				  case "staff":
					  $mailer = Mailer::sendMail();
					  $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));
	
					  $userrow = self::$db->select(Users::aTable, array('email', 'CONCAT(fname," ",lname) as name'), array('userlevel <>'=>9))->results();
	
					  $replacements = array();
					  if ($userrow) {
						  foreach ($userrow as $cols) {
							  $replacements[$cols->email] = array(
								  '[COMPANY]' => App::get("Core")->company,
								  '[LOGO]' => Utility::getLogo(),
								  '[NAME]' => $cols->name,
								  '[URL]' => SITEURL,
								  '[DATE]' => date('Y'));
						  }
	
						  $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
						  $mailer->registerPlugin($decorator);
	
						  $message = Swift_Message::newInstance()
									->setSubject($subject)
									->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
									->setBody($body, 'text/html');
	
						  foreach ($userrow as $row) {
							  $message->setTo(array($row->email => $row->name));
							  $numSent++;
							  $mailer->send($message, $failedRecipients);
						  }
						  unset($row);
	
					  }
					  break;
	
				  case "sellers":
					  $mailer = Mailer::sendMail();
					  $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

					  $userrow = self::$db->select(Users::mTable, array('email', 'CONCAT(fname," ",lname) as name'), array('listings >'=>0))->results();
	
					  $replacements = array();
					  if ($userrow) {
						  foreach ($userrow as $cols) {
							  $replacements[$cols->email] = array(
								  '[COMPANY]' => App::get("Core")->company,
								  '[LOGO]' => Utility::getLogo(),
								  '[NAME]' => $cols->name,
								  '[URL]' => SITEURL,
								  '[DATE]' => date('Y'));
						  }
	
						  $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
						  $mailer->registerPlugin($decorator);
	
						  $message = Swift_Message::newInstance()
									->setSubject($subject)
									->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
									->setBody($body, 'text/html');
	
						  foreach ($userrow as $row) {
							  $message->setTo(array($row->email => $row->name));
							  $numSent++;
							  $mailer->send($message, $failedRecipients);
						  }
						  unset($row);
					  }
					  break;

				  case "newsletter":
					  $mailer = Mailer::sendMail();
					  $mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin(100, 30));

					  $userrow = self::$db->select(self::nwTable, array('email', 'name'))->results();
	
					  $replacements = array();
					  if ($userrow) {
						  foreach ($userrow as $cols) {
							  $replacements[$cols->email] = array(
								  '[COMPANY]' => App::get("Core")->company,
								  '[LOGO]' => Utility::getLogo(),
								  '[NAME]' => $cols->name,
								  '[URL]' => SITEURL,
								  '[DATE]' => date('Y'));
						  }
	
						  $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
						  $mailer->registerPlugin($decorator);
	
						  $message = Swift_Message::newInstance()
									->setSubject($subject)
									->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
									->setBody($body, 'text/html');
	
						  foreach ($userrow as $row) {
							  $message->setTo(array($row->email => $row->name));
							  $numSent++;
							  $mailer->send($message, $failedRecipients);
						  }
						  unset($row);
					  }
					  break;
					  
				  default:
					  $mailer = Mailer::sendMail();
					  $table = isset($_POST['clients']) ? Users::mTable : Users::aTable;
					  $row = self::$db->pdoQuery("SELECT email, CONCAT(fname,' ',lname) as name FROM `" . $table . "` WHERE email LIKE '%" . Validator::sanitize($to) . "%'")->result();
					  if ($row) {
						  $newbody = str_replace(array(
							  '[COMPANY]',
							  '[LOGO]',
							  '[NAME]',
							  '[URL]',
							  '[DATE]'), array(
							  App::get("Core")->company,
							  Utility::getLogo(),
							  $row->name,
							  SITEURL,
							  date('Y')), $body);
	
						  $message = Swift_Message::newInstance()
									->setSubject($subject)->setTo(array($to => $row->name))
									->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
									->setBody($newbody, 'text/html');
	
						  $numSent++;
						  $mailer->send($message, $failedRecipients);
					  }
					  break;
			  }
	
			  if ($numSent) {
				  $json['type'] = 'success';
				  $json['title'] = Lang::$word->SUCCESS;
				  $json['message'] = $numSent . ' ' . Lang::$word->EMN_SENT;
			  } else {
				  $json['type'] = 'error';
				  $json['title'] = Lang::$word->ERROR;
				  $res = '';
				  $res .= '<ul>';
				  foreach ($failedRecipients as $failed) {
					  $res .= '<li>' . $failed . '</li>';
				  }
				  $res .= '</ul>';
				  $json['message'] = Lang::$word->EMN_ALERT . $res;
	
				  unset($failed);
			  }
			  print json_encode($json);
	
		  } else {
			  Message::msgSingleStatus();
		  }
	
	  }
	  
      /**
       * Content::getPages()
       * 
       * @return
       */
      public function getPages()
      {
		  
		  $row = self::$db->select(self::pgTable)->results();

          return ($row) ? $row : 0;

      }

      /**
       * Content::renderPage()
       * 
       * @return
       */
      public function renderPage()
      {
		  
		  $row = self::$db->select(self::pgTable, "*", array("slug" => App::get('Core')->_url[1], "active" => 1, "home_page" => 0))->result();

          return ($row) ? $row : 0;

      }
	  
      /**
       * Content::processPage()
       * 
       * @return
       */
      public function processPage()
      {
		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('title', 'string', true, 3, 150, Lang::$word->PAG_NAME);
		  $validate->addRule('slug', 'string', false);
		  $validate->addRule('created_submit', 'date', false);
		  $validate->addRule('active', 'numeric', false, 1, 1);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'title' => $validate->safe->title, 
					'slug' => (empty($_POST['slug'])) ? Url::doSeo($validate->safe->title) : Url::doSeo($validate->safe->slug),
					'body' => $_POST['body'],
					'created' => empty($validate->safe->created_submit) ? Db::toDate() : $validate->safe->created_submit, 
					'contact' => $_POST['contact'], 
					'faq' => $_POST['faq'], 
					'home_page' => $_POST['home_page'], 
					'active' => $validate->safe->active
			  );
              
			  
			  if ($data['home_page'] == 1) {
				  self::$db->pdoQuery("UPDATE `" . self::pgTable . "` SET `home_page`= DEFAULT(home_page);");
				  $sdata['home_content'] = $data['body'];
				  self::$db->update(Core::sTable, $sdata, array('id' => 1));
			  }	
			  
			  if ($data['contact'] == 1) {
				  self::$db->pdoQuery("UPDATE `" . self::pgTable . "` SET `contact`= DEFAULT(contact);");
			  }	
			  
			  if ($data['faq'] == 1) {
				  self::$db->pdoQuery("UPDATE `" . self::pgTable . "` SET `faq`= DEFAULT(faq);");
			  }	
			  
			  (Filter::$id) ? self::$db->update(self::pgTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::pgTable, $data);
			  $message = (Filter::$id) ? Lang::$word->PAG_UPDATED : Lang::$word->PAG_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getMenus()
       * 
       * @return
       */
      public function getMenus()
      {
		  
		  $row = self::$db->select(self::muTable, "*", null, "ORDER BY position")->results();

          return ($row) ? $row : 0;

      }

      /**
       * Content::renderMenus()
       * 
       * @return
       */
      public function renderMenus()
      {

		  $sql = "
		  SELECT 
			m.*,
			p.slug AS pslug,
			p.home_page 
		  FROM
			`" . self::muTable . "` AS m 
			LEFT JOIN `" . self::pgTable . "` AS p 
			  ON m.page_id = p.id 
		  WHERE m.active = ? 
		  ORDER BY m.position;";
		  
		  $row = self::$db->pdoQuery($sql, array(1))->results();

          return ($row) ? $row : 0;

      }
	  
	  /**
	   * Content::processMenu()
	   * 
	   * @return
	   */
	  public function processMenu()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 150, Lang::$word->MENU_NAME);
		  $validate->addRule('content_type', 'string', true, 3, 20, Lang::$word->MENU_TYPE);
		  $validate->addRule('active', 'numeric', false, 1, 1);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
				  'slug' => Url::doSeo($validate->safe->name),
				  'page_id' => ($_POST['content_type'] == "web") ? 0 : intval($_POST['page_id']),
				  'content_type' => $validate->safe->content_type,
				  'link' => (!empty($_POST['web'])) ? Validator::sanitize($_POST['web']) : "NULL",
				  'target' => (!empty($_POST['target'])) ? Validator::sanitize($_POST['target']) : "_self",
				  'active' => $validate->safe->active
			  );

			  (Filter::$id) ? self::$db->update(self::muTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::muTable, $data);
			  $message = (Filter::$id) ? Lang::$word->MENU_UPDATED : Lang::$word->MENU_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }
	  
      /**
       * Content::getSortMenuList()
       * 
       * @return
       */
	  public function getSortMenuList()
	  {
		  
		  if ($menurow = self::$db->select(self::muTable, null, null, 'ORDER BY position')->results()) {
			  print "<ol class=\"dd-list lagre\">\n";
			  foreach ($menurow as $row) {
				  print '
				    <li data-id="' . $row->id . '" class="dd-item dd3-item">'
				  .'<div class="dd-handle dd3-handle"></div>' 
				  .'<div class="dd3-content"><a href="' . Url::adminUrl("menus", "edit", false,"?id=" . $row->id) . '">' . $row->name . '</a>' 
				  .'<span><a class="delete" data-set=\'{"title": "' . Lang::$word->MENU_DELETE . '", "parent": "li", "option": "deleteMenu", "id": ' . $row->id . ', "name": "' . $row->name . '"}\'><i class="icon negative delete"></i></a></span></div>';
				  print "</li>\n";
			  }
		  }
		  unset($row);
		  print "</ol>\n";
	  }
	  
      /**
       * Content::getContentType()
       * 
	   * @param bool $selected
       * @return
       */ 	  
      public static function getContentType($selected = false)
	  {
		  $arr = array(
				'page' => Lang::$word->MENU_CPAGE,
				'web' => Lang::$word->MENU_ELINK
		  );
		  
		  $contenttype = '';
		  foreach ($arr as $key => $val) {
              if ($key == $selected) {
                  $contenttype .= "<option selected=\"selected\" value=\"" . $key . "\">" . $val . "</option>\n";
              } else
                  $contenttype .= "<option value=\"" . $key . "\">" . $val . "</option>\n";
          }
          unset($val);
          return $contenttype;
      } 
	  
      /**
       * Content::getFaq()
       * 
       * @return
       */
      public function getFaq()
      {
		  
		  $row = self::$db->select(self::faqTable, "*", null, 'ORDER BY sorting')->results();
          return ($row) ? $row : 0;

      }
	  
	  /**
	   * Content::processFaq()
	   * 
	   * @return
	   */
	  public function processFaq()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('question', 'string', true, 5, 200, Lang::$word->FAQ_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'question' => $validate->safe->question, 
				  'answer' => $_POST['answer']
			  );

			  (Filter::$id) ? self::$db->update(self::faqTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::faqTable, $data);
			  $message = (Filter::$id) ? Lang::$word->FAQ_UPDATED : Lang::$word->FAQ_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getNews()
       * 
       * @return
       */
      public function getNews()
      {
		  
		  $row = self::$db->select(self::nwaTable, "*", null, 'ORDER BY created DESC')->results();
          return ($row) ? $row : 0;

      }

      /**
       * Content::renderNews()
       * 
       * @return
       */
      public function renderNews()
      {
		  
		  $row = self::$db->first(self::nwaTable, "*", array("active" => 1));
          return ($row) ? $row : 0;

      }
	  
      /**
       * Content::processNews()
       * 
       * @return
       */
      public function processNews()
      {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('title', 'string', true, 3, 100, Lang::$word->NWA_NAME);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'title' => $validate->safe->title, 
					'body' => $_POST['body'], 
					'created' => Db::toDate(), 
					'author' => App::get('Auth')->username,
					'active' => isset($_POST['active']) ? 1 : 0,
			  );

			  if ($data['active'] == 1) {
				  self::$db->pdoQuery("UPDATE `" . self::nwaTable . "` SET `active`= DEFAULT(active);");
			  }	
			  
			  (Filter::$id) ? self::$db->update(self::nwaTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::nwaTable, $data);
			  $message = (Filter::$id) ? Lang::$word->NWA_UPDATED : Lang::$word->NWA_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }
			  
      /**
       * Content::getFeatures()
       * 
       * @return
       */
      public function getFeatures()
      {
		  $row = self::$db->select(self::fTable, "*", null, 'ORDER BY sorting')->results();
          return ($row) ? $row : 0;
      }

      /**
       * Content::getFeaturesById()
       * 
       * @return
       */
      public function getFeaturesById($id)
      {
          $ids = ($id) ? $id : 0;
          $sql = "SELECT * FROM `" . self::fTable . "` WHERE id IN(" . $ids . ") ORDER BY name";
          $row = self::$db->pdoQuery($sql)->results();

          return ($row) ? $row : 0;
      }
	  
	  /**
	   * Content::processFeature()
	   * 
	   * @return
	   */
	  public function processFeature()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->FEAT_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
			  );

			  self::$db->insert(self::fTable, $data);
			  Message::msgReply(self::$db->affected(), 'success', str_replace("[NAME]", $data['name'], Lang::$word->FEAT_ADDED));
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getModels()
       * 
       * @return
       */
      public function getModels()
      {

          if (Filter::$id) {
			  $counter = self::$db->count(false, false, "SELECT COUNT(*) FROM `" . self::mdTable . "` WHERE make_id = " . Filter::$id . " LIMIT 1");
              $where = "WHERE md.make_id = " . Filter::$id;
          } elseif(isset($_POST['find'])) {
			  $counter = self::$db->count(false, false, "SELECT COUNT(*) FROM `" . self::mdTable . "` WHERE name LIKE '%" . Validator::sanitize($_POST['find']) . "%' LIMIT 1");
              $where = "WHERE md.name LIKE '%" . Validator::sanitize($_POST['find']) . "%'";
          } else {
			  $counter = self::$db->count(self::mdTable);
              $where = null;
          }

          $pager = Paginator::instance();
          $pager->items_total = $counter;
          $pager->default_ipp = App::get("Core")->perpage;
          $pager->path = Url::adminUrl("models", false, false, "?");
          $pager->paginate();
		  
          $sql = "
		  SELECT 
			md.id AS mdid,
			md.name AS mdname,
			mk.name AS mkname 
		  FROM
			`" . self::mdTable . "` AS md 
			LEFT JOIN `" . self::mkTable . "` AS mk 
			  ON mk.id = md.make_id 
		  $where
		  ORDER BY md.name 
		  " . $pager->limit;
          $row = self::$db->pdoQuery($sql)->results();

          return ($row) ? $row : 0;
      }

      /**
       * Content::getModelList()
       * 
       * @return
       */
      public function getModelList($make_id)
      {
		  $row = self::$db->select(self::mdTable, "*", array("make_id" => $make_id), 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }
	  
      /**
       * Content::processModel()
       * 
       * @return
       */
      public function processModel()
      {
          if(empty($_POST['id']))
			  $err = Message::$msgs['id'] = Lang::$word->MAKE_NAME_R;

          $name = array_filter($_POST['modelname'], 'strlen');
          if (empty($name))
              $err = Message::$msgs['answer'] = Lang::$word->MODL_NAME_R;

          if (empty(Message::$msgs)) {
			  $makename = self::$db->first(self::mkTable, array("name"), array('id' => Filter::$id));
              $html = '';
              foreach ($_POST['modelname'] as $key => $val) {
                  $data = array('name' => Validator::sanitize($_POST['modelname'][$key]), 'make_id' => Filter::$id);
                  $last_id = self::$db->insert(self::mdTable, $data)->getLastInsertId();

                  $html .= '
				  <tr>
					<td class="warning"><small>' . $last_id . '.</small></td>
					<td>' . $makename->name . '</td>
					<td data-editable="true" data-set=\'{"type": "model", "id": ' . $last_id . ',"key":"name", "path":""}\'>' . $data['name'] . '</td>
					<td><a class="delete" data-set=\'{"title": "' . Lang::$word->MODL_DEL . '", "parent": "tr", "option": "deleteModel", "id": ' . $last_id . ', "name": "' . $data['name'] . '"}\'><i class="rounded outline icon negative trash link"></i></a></td>
				  </tr>';
              }
			  $json = array(
				  'type' => 'success',
				  'title' => Lang::$word->SUCCESS,
				  'data' => $html,
				  'message' => Lang::$word->MODL_ADDED
				  );
              print json_encode($json);

		  } else {
			  $json['type'] = 'error';
			  $json['title'] = Lang::$word->ERROR;
			  $json['message'] = $err;
			  print json_encode($json);
		  }

      }
	  
      /**
       * Content::getMakes()
       * 
       * @return
       */
      public function getMakes($paginate = true)
      {
          if ($paginate) {
              $pager = Paginator::instance();
              $pager->items_total = self::$db->count(self::mkTable);
              $pager->default_ipp = App::get("Core")->perpage;
			  $pager->path = Url::adminUrl("makes", false, false, "?");
              $pager->paginate();
              $limit = $pager->limit;
          } else {
              $limit = null;
          }
		  
		  $row = self::$db->select(self::mkTable, "*", null, 'ORDER BY name' . $limit)->results();
          return ($row) ? $row : 0;
      }
	  
	  /**
	   * Content::processMake()
	   * 
	   * @return
	   */
	  public function processMake()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->MAKE_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name,
				  'name_slug' => Url::doSeo($validate->safe->name)
			  );

			  self::$db->insert(self::mkTable, $data);
			  Message::msgReply(self::$db->affected(), 'success', str_replace("[NAME]", $data['name'], Lang::$word->MAKE_ADDED));
		  } else {
			  Message::msgSingleStatus();
		  }
      }
	  
      /**
       * Content::getConditions()
       * 
       * @return
       */
      public function getConditions()
      {
		  $row = self::$db->select(self::cdTable, "*", null, 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }
	  
	  /**
	   * Content::processCondition()
	   * 
	   * @return
	   */
	  public function processCondition()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->COND_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
			  );

			  self::$db->insert(self::cdTable, $data);
			  Message::msgReply(self::$db->affected(), 'success', str_replace("[NAME]", $data['name'], Lang::$word->COND_ADDED));
			  $sdata['cond_list_alt'] = serialize($this->getConditions());
			  self::$db->update(Core::sTable, $sdata, array("id" => 1));
		  } else {
			  Message::msgSingleStatus();
		  }
      }
	  
      /**
       * Content::getFuel()
       * 
       * @return
       */
      public function getFuel()
      {
		  $row = self::$db->select(self::fuTable, "*", null, 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }
	  
	  /**
	   * Content::processFuel()
	   * 
	   * @return
	   */
	  public function processFuel()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->FUEL_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
			  );

			  self::$db->insert(self::fuTable, $data);
			  Message::msgReply(self::$db->affected(), 'success', str_replace("[NAME]", $data['name'], Lang::$word->FUEL_ADDED));
			  $sdata['fuel_list'] = json_encode($this->getFuel());
			  self::$db->update(Core::sTable, $sdata, array("id" => 1));
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getTransmissions()
       * 
       * @return
       */
      public function getTransmissions()
      {
		  $row = self::$db->select(self::trTable, "*", null, 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }

	  /**
	   * Content::processTransmission()
	   * 
	   * @return
	   */
	  public function processTransmission()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->TRNS_NAME);
		  $validate->run();
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
			  );

			  self::$db->insert(self::trTable, $data);
			  Message::msgReply(self::$db->affected(), 'success', str_replace("[NAME]", $data['name'], Lang::$word->TRNS_ADDED));
			  $sdata['trans_list'] = json_encode($this->getTransmissions());
			  self::$db->update(Core::sTable, $sdata, array("id" => 1));
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getCoupons()
       * 
       * @return
       */
      public function getCoupons()
      {
		  $sql = "
		  SELECT *,
			CASE
			  WHEN type = 'a' 
			  THEN '" . Lang::$word->DC_TYPE_A . "' 
			  ELSE '" . Lang::$word->DC_TYPE_P . "' 
			END type
		  FROM `" . self::dcTable . "` 
		  ORDER BY created DESC;";
			  
		  $row = self::$db->pdoQuery($sql)->results();
          return ($row) ? $row : 0;
      }

      /**
       * Content::processCoupon()
       * 
       * @return
       */
      public function processCoupon()
      {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('title', 'string', true, 3, 100, Lang::$word->DC_NAME);
		  $validate->addRule('code', 'string', true, 3, 20, Lang::$word->DC_CODE);
		  $validate->addRule('discount', 'numeric', true, 1, 2, Lang::$word->DC_DISC);
		  $validate->addRule('type', 'string', false);
		  $validate->addRule('active', 'numeric', false);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'title' => $validate->safe->title, 
					'code' => $validate->safe->code, 
					'discount' => $validate->safe->discount, 
					'type' => $validate->safe->type, 
					'mid' => isset($_POST['mid']) ? Utility::implodeFields($_POST['mid']) : 0, 
					'active' => $validate->safe->active
			  );

			  (Filter::$id) ? self::$db->update(self::dcTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::dcTable, $data);
			  $message = (Filter::$id) ? Lang::$word->DC_UPDATED : Lang::$word->DC_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getSlider()
       * 
       * @return
       */
      public function getSlider()
      {

		  $row = self::$db->select(self::slTable, null, null, "ORDER BY sorting")->results();

          return ($row) ? $row : 0; 

      }

	  /**
	   * Content::processSlide()
	   * 
	   * @return
	   */
	  public function processSlide()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('caption', 'string', true, 3, 200, Lang::$word->SLD_NAME);
		  $validate->run();
		  
		  if(!Filter::$id and empty($_FILES['thumb']['name'])){
			  Message::$msgs['thumb'] = Lang::$word->SLD_IMAGE;
		  }
		  
          if (!empty($_FILES['thumb']['name']) and empty(Message::$msgs)) {
			  $upl = Upload::instance(2097152, "png,jpg");
              $upl->process("thumb", UPLOADS . "slider/", "SLIDE_");
          }
		  
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'caption' => $validate->safe->caption, 
				  'body' => $_POST['body'],
			  );

			  /* == Procces Image == */
			  if (!empty($_FILES['thumb']['name'])) {
				  $thumbdir = UPLOADS . "slider/";
				  if (Filter::$id && $row = self::$db->first(self::slTable, array("thumb"), array('id' => Filter::$id))) {
					  File::deleteFile($thumbdir . $row->thumb);
				  }
				  $data['thumb'] = $upl->fileInfo['fname'];
			  }
			  
			  (Filter::$id) ? self::$db->update(self::slTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::slTable, $data);
			  $message = (Filter::$id) ? Lang::$word->SLD_UPDATED : Lang::$word->SLD_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getReviews()
       * 
       * @return
       */
      public function getReviews($status = false)
      {
		  $active = $status ? 'WHERE r.status = 1' : null;
		  $sql ="
		  SELECT r.*,
			CONCAT(m.fname,' ',m.lname) as name,
			m.avatar,
			m.id AS uid
		  FROM
			`" . self::rwTable . "` AS r 
			LEFT JOIN `" . Users::mTable . "` AS m 
			  ON m.id = r.user_id 
		  $active
		  ORDER BY r.created DESC;";
		  
		  $row = self::$db->pdoQuery($sql)->results();
          return ($row) ? $row : 0;
      }

      /**
       * Content::addReview()
       * 
       * @return
       */
      public function addReview()
      {
		  $validate = Validator::instance();
		  $validate->addSource($_POST);

		  $validate->addRule('content', 'string', true, 20, 300, Lang::$word->SRW_DESC);
		  $validate->addRule('twitter', 'string', false);

		  $validate->run();
          if (empty(Message::$msgs)) {
              $data = array(
					'content' => $validate->safe->content, 
					'twitter' => $validate->safe->twitter, 
					'user_id' => App::get('Auth')->uid
					
			  );
              $last_id = self::$db->insert(self::rwTable, $data)->getLastInsertId();
			  
			  if ($last_id) {
				  $json['type'] = "success";
				  $json['title'] = Lang::$word->SRW_ADDDED;
				  $json['message'] = Lang::$word->M_ADDED;
				  $json['redirect'] = Url::doUrl(URL_ACCOUNT);
			  } else {
				  $json['type'] = "alert";
				  $json['title'] = Lang::$word->ALERT;
				  $json['message'] = Lang::$word->NOPROCCESS;
			  }
			  
			  print json_encode($json);
			  
			  if ($last_id) {
				  $mailer = Mailer::sendMail();
	
				  ob_start();
				  require_once (BASEPATH . 'mailer/' . Core::$language . '/Admin_Notify_Review.tpl.php');
				  $html_message = ob_get_contents();
				  ob_end_clean();
	
				  $body = str_replace(array(
					  '[LOGO]',
					  '[USERNAME]',
					  '[NAME]',
					  '[CONTENT]',
					  '[IP]',
					  '[DATE]',
					  '[COMPANY]',
					  '[SITEURL]'), array(
					  Utility::getLogo(),
					  App::get('Auth')->username,
					  App::get('Auth')->name,
					  $validate->safe->content,
					  Url::getIP(),
					  date('Y'),
					  App::get("Core")->company,
					  SITEURL), $html_message);
	
				  $msg = Swift_Message::newInstance()
						->setSubject(Lang::$word->SRW_SUBJECT . ' ' . App::get('Auth')->name)
						->setTo(array(App::get("Core")->site_email => App::get("Core")->company))
						->setFrom(array(App::get("Core")->site_email => App::get("Core")->company))
						->setBody($body, 'text/html');
	
				  $mailer->send($msg);
			  }
		  } else {
			  Message::msgSingleStatus();
		  }

	  }
	  
      /**
       * Content::getCategories()
       * 
       * @return
       */
      public function getCategories()
      {
		  $row = self::$db->select(self::ctTable, "*", null, 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }

      /**
       * Content::getCategoryCounters()
       * 
       * @return
       */
      public function getCategoryCounters()
      {

		  $sql = "
		  SELECT 
			c.id,
			c.name,
			c.slug,
			c.image,
			COUNT(l.id) AS listings 
		  FROM
			`" . self::ctTable . "` c 
			LEFT JOIN `" . Items::lTable . "` l 
			  ON l.category = c.id 
		  WHERE l.status = 1
		  AND l.featured = 1
		  GROUP BY c.id LIMIT " . App::get('Core')->featured; 
		  
		  $row = self::$db->pdoQuery($sql)->results();
          return ($row) ? $row : 0;
      }
	  
	  /**
	   * Content::processCategory()
	   * 
	   * @return
	   */
	  public function processCategory()
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->MAKE_NAME);
		  $validate->addRule('slug', 'string', false);
		  
          if (!empty($_FILES['image']['name']) and empty(Message::$msgs)) {
			  $upl = Upload::instance(1048576, "png,jpg");
              $upl->process("image", UPLOADS . "catico/", false);
          }
		  
		  $validate->run();
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name, 
				  'slug' => (empty($_POST['slug'])) ? Url::doSeo($validate->safe->name) : Url::doSeo($validate->safe->slug),
			  );

			  /* == Procces Icon == */
			  if (!empty($_FILES['image']['name'])) {
				  $thumbdir = UPLOADS . "catico/";
				  if (Filter::$id && $row = self::$db->first(self::ctTable, array("image"), array('id' => Filter::$id))) {
					  File::deleteFile($thumbdir . $row->image);
				  }
				  $data['image'] = $upl->fileInfo['fname'];
			  }
			  
			  (Filter::$id) ? self::$db->update(self::ctTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::ctTable, $data);
			  $message = (Filter::$id) ? Lang::$word->CAT_UPDATED : Lang::$word->CAT_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }

      /**
       * Content::getLocations()
       * 
       * @return
       */
      public function getLocations($owner = true)
      {
		  $is_owner = $owner ? array("ltype" => "owner") : null;
		  
		  $row = self::$db->select(self::lcTable, "*", $is_owner, 'ORDER BY name')->results();
          return ($row) ? $row : 0;

      }

      /**
       * Content::getUserLocations()
       * 
       * @return
       */
      public function getUserLocations()
      {
		  
		  $row = self::$db->select(self::lcTable, "*", array("user_id" => App::get('Auth')->uid), 'ORDER BY name')->results();
          return ($row) ? $row : 0;

      }
	  
	  /**
	   * Content::processLocation()
	   * 
	   * @return
	   */
	  public function processLocation($front = false)
	  {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('name', 'string', true, 3, 200, Lang::$word->LOC_NAME);
		  $validate->addRule('email', 'email');
		  $validate->addRule('address', 'string', true, 3, 200, Lang::$word->ADDRESS);
		  $validate->addRule('city', 'string', true, 2, 100, Lang::$word->CITY);
		  $validate->addRule('state', 'string', true, 2, 50, Lang::$word->STATE);
		  $validate->addRule('zip', 'string', true, 3, 30, Lang::$word->ZIP);
		  $validate->addRule('country', 'string', true, 3, 30, Lang::$word->COUNTRY);
		  $validate->addRule('phone', 'string', false);
		  $validate->addRule('fax', 'string', false);
		  $validate->addRule('url', 'string', false);
		  $validate->addRule('lat', 'float', false);
		  $validate->addRule('lng', 'float', false);
		  $validate->addRule('zoom', 'numeric', false);
		  
          if (!empty($_FILES['logo']['name']) and empty(Message::$msgs)) {
			  $upl = Upload::instance(1048576, "png,jpg");
              $upl->process("logo", UPLOADS . "showrooms/", "logo_");
          }
		  
		  $validate->run();
		  if (empty(Message::$msgs)) {
			  $data = array(
				  'name' => $validate->safe->name,
				  'name_slug' => Url::doSeo(Utility::randNumbers(4) . '-' . $validate->safe->name), 
				  'ltype' => $front ? "user" : "owner",
				  'user_id' => $front ? App::get('Auth')->uid : 0,
				  'email' => $validate->safe->email,
				  'address' => $validate->safe->address,
				  'city' => $validate->safe->city,
				  'state' => $validate->safe->state,
				  'zip' => $validate->safe->zip,
				  'country' => $validate->safe->country,
				  'phone' => $validate->safe->phone,
				  'fax' => $validate->safe->fax,
				  'url' => $validate->safe->url,
				  'lat' => $validate->safe->lat,
				  'lng' => $validate->safe->lng,
				  'zoom' => $validate->safe->zoom,
			  );

			  /* == Procces Icon == */
			  if (!empty($_FILES['logo']['name'])) {
				  $thumbdir = UPLOADS . "showrooms/";
				  if (Filter::$id && $row = self::$db->first(self::lcTable, array("logo"), array('id' => Filter::$id))) {
					  if($row->logo) {
						  File::deleteFile($thumbdir . $row->logo);
					  }
				  }
				  $data['logo'] = $upl->fileInfo['fname'];
			  }
			  
			  (Filter::$id) ? self::$db->update(self::lcTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::lcTable, $data);
			  $message = (Filter::$id) ? Lang::$word->LOC_UPDATED : Lang::$word->LOC_ADDED;
			  if($front) {
				  $json['type'] = "success";
				  $json['title'] = Lang::$word->SUCCESS;
				  $json['message'] = $message;
				  $json['redirect'] = Url::doUrl(URL_MYLOCATIONS);
				  print json_encode($json);
			  } else {
				  Message::msgReply(self::$db->affected(), 'success', $message);
			  }
		  } else {
			  Message::msgSingleStatus();
		  }
      }
	  
      /**
       * Content::getGetaways()
       * 
       * @return
       */
      public function getGetaways($active = false)
      {
          $is_active = $active ? array("active" =>1) : null;
		  $row = self::$db->select(self::gwTable, "*", $is_active, 'ORDER BY name')->results();
          return ($row) ? $row : 0;
      }

      /**
       * Content::processGateway()
       * 
       * @return
       */
      public function processGateway()
      {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('displayname', 'string', true, 3, 200, Lang::$word->GW_NAME);
		  $validate->addRule('extra', 'string', false);
		  $validate->addRule('extra2', 'string', false);
		  $validate->addRule('extra3', 'string', false);
		  $validate->addRule('active', 'numeric', false);
		  $validate->addRule('live', 'numeric', false);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'displayname' => $validate->safe->displayname, 
					'extra' => $validate->safe->extra, 
					'extra2' => $validate->safe->extra2, 
					'extra3' => $validate->safe->extra3, 
					'live' => $validate->safe->live, 
					'active' => $validate->safe->active
			  );

              self::$db->update(self::gwTable, $data, array('id' => Filter::$id));
              $message = Lang::$word->GW_UPDATED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
          } else {
              Message::msgSingleStatus();
          }
      }

      /**
       * Content::getMemberships()
       * 
       * @return
       */
	  public function getMemberships($private = false)
	  {
		  $is_private = $private ? array("private" => 0, "active" => 1) : null;
		  $row = self::$db->select(self::msTable, "*", $is_private, 'ORDER BY price')->results();
          return ($row) ? $row : 0;
	  }
	  
      /**
       * Content::processPackage()
       * 
       * @return
       */
      public function processPackage()
      {

		  $validate = Validator::instance();
		  $validate->addSource($_POST);
		  $validate->addRule('title', 'string', true, 3, 200, Lang::$word->MSM_NAME);
		  $validate->addRule('price', 'float', true, 0, 0, Lang::$word->MSM_PRICE);
		  $validate->addRule('days', 'numeric', true, 1, 3, Lang::$word->MSM_PERIOD);
		  $validate->addRule('period', 'string', false);
		  $validate->addRule('private', 'numeric', false);
		  $validate->addRule('featured', 'numeric', false);
		  $validate->addRule('active', 'numeric', false);
		  $validate->addRule('listings', 'numeric', false);
		  $validate->addRule('description', 'string', false);
		  $validate->run();
		  
          if (empty(Message::$msgs)) {
              $data = array(
					'title' => $validate->safe->title, 
					'price' => $validate->safe->price, 
					'days' => $validate->safe->days, 
					'period' => $validate->safe->period, 
					'private' => $validate->safe->private, 
					'featured' => $validate->safe->featured,
					'active' => $validate->safe->active,
					'listings' => $validate->safe->listings,
					'description' => $validate->safe->description,
			  );

			  (Filter::$id) ? self::$db->update(self::msTable, $data, array('id' => Filter::$id)) : self::$db->insert(self::msTable, $data);
			  $message = (Filter::$id) ? Lang::$word->MSM_UPDATED : Lang::$word->MSM_ADDED;
			  Message::msgReply(self::$db->affected(), 'success', $message);
		  } else {
			  Message::msgSingleStatus();
		  }
      }
	  
      /**
       * Content::getPayments()
       * 
       * @param bool $from
       * @return
       */
	  public function getPayments($from = false)
	  {

		  if (Filter::$id and (isset($_POST['fromdate_submit']) && $_POST['fromdate_submit'] <> "" || isset($from) && $from != '')) {
              $enddate = date("Y-m-d");
              $fromdate = (empty($from)) ? Validator::sanitize($_POST['fromdate_submit']) : $from;
              if (isset($_POST['enddate_submit']) && $_POST['enddate_submit'] <> "") {
                  $enddate = Validator::sanitize($_POST['enddate_submit']);
              }
			  $counter = self::$db->count(false, false, "SELECT COUNT(*) FROM `" . self::txTable . "` WHERE p.membership_id = " . Filter::$id . " AND created BETWEEN '" . trim($fromdate) . "' AND '" . trim($enddate) . " 23:59:59'");
			  $where = " WHERE p.membership_id = " . Filter::$id . " AND p.created BETWEEN '" . trim($fromdate) . "' AND '" . trim($enddate) . " 23:59:59'"; 
		  } elseif (isset($_POST['fromdate_submit']) && $_POST['fromdate_submit'] <> "" || isset($from) && $from != '') {
              $enddate = date("Y-m-d");
              $fromdate = (empty($from)) ? Validator::sanitize($_POST['fromdate_submit']) : $from;
              if (isset($_POST['enddate_submit']) && $_POST['enddate_submit'] <> "") {
                  $enddate = Validator::sanitize($_POST['enddate_submit']);
              }
			  $counter = self::$db->count(false, false, "SELECT COUNT(*) FROM `" . self::txTable . "` WHERE created BETWEEN '" . trim($fromdate) . "' AND '" . trim($enddate) . " 23:59:59'");
			  $where = " WHERE p.created BETWEEN '" . trim($fromdate) . "' AND '" . trim($enddate) . " 23:59:59'";
          } elseif (Filter::$id) {
              $counter = self::$db->count(false, false, "SELECT COUNT(*) FROM `" . self::txTable . "` WHERE membership_id = " . Filter::$id . " LIMIT 1");
			  $where = " WHERE p.membership_id = " . Filter::$id;
		  } else {
			  $counter = self::$db->count(self::txTable);
			  $where = null;
		  }
	
          $pager = Paginator::instance();
          $pager->items_total = $counter;
          $pager->default_ipp = App::get("Core")->perpage;
          $pager->path = Url::adminUrl("transactions", false, false, "?");
          $pager->paginate();
	
		  $sql = "
		  SELECT 
			p.*,
			p.id AS id,
			u.username,
			u.id AS uid,
			m.id AS mid,
			m.title 
		  FROM
			`" . self::txTable . "` AS p 
			LEFT JOIN `" . Users::mTable . "` AS u 
			  ON u.id = p.user_id 
			LEFT JOIN `" . self::msTable . "` AS m 
			  ON m.id = p.membership_id 
		  $where
		  ORDER BY p.created DESC " . $pager->limit; 
	
		  $row = self::$db->pdoQuery($sql)->results();
		  return ($row) ? $row : 0;
	  }

      /**
       * Content::getUserTransactions()
       * 
	   * @param bool $uid
       * @return
       */
	  public function getUserTransactions($uid = false)
	  {
		  $id = $uid ? $uid : Filter::$id;
		  
		  $sql = "
		  SELECT 
			p.*,
			p.id AS id,
			m.id AS mid,
			m.title 
		  FROM
			`" . self::txTable . "` AS p 
			LEFT JOIN `" . self::msTable . "` AS m 
			  ON m.id = p.membership_id 
		  WHERE user_id = ?
		  ORDER BY p.created DESC;"; 
		  
          $row = self::$db->pdoQuery($sql, array($id))->results();
          return ($row) ? $row : 0;
	  }
	  
      /**
       * Content::colorList()
       * 
       * @return
       */
      public static function colorList()
      {
          $data = array(
              Lang::$word->WHITE => Lang::$word->WHITE,
              Lang::$word->BLACK => Lang::$word->BLACK,
              Lang::$word->SILVER => Lang::$word->SILVER,
              Lang::$word->GRAY => Lang::$word->GRAY,
              Lang::$word->RED => Lang::$word->RED,
              Lang::$word->BLUE => Lang::$word->BLUE,
			  Lang::$word->BEIGE => Lang::$word->BEIGE,
			  Lang::$word->YELLOW => Lang::$word->YELLOW,
			  Lang::$word->GREEN => Lang::$word->GREEN,
			  Lang::$word->BROWN => Lang::$word->BROWN,
			  Lang::$word->BURGUNDY => Lang::$word->BURGUNDY,
			  Lang::$word->CHARCOAL => Lang::$word->CHARCOAL,
			  Lang::$word->GOLD => Lang::$word->GOLD,
			  Lang::$word->PINK => Lang::$word->PINK,
			  Lang::$word->PURPLE => Lang::$word->PURPLE,
			  Lang::$word->TAN => Lang::$word->TAN,
			  Lang::$word->TURQUOISE => Lang::$word->TURQUOISE,
			  );
          return $data;
      }
  }