<?php

final class PhabricatorCountdownListController
  extends PhabricatorCountdownController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setOffset($request->getInt('page'));
    $pager->setURI($request->getRequestURI(), 'page');

    $timers = id(new PhabricatorTimer())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);

    $timers = $pager->sliceResults($timers);

    $phids = mpull($timers, 'getAuthorPHID');
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($timers as $timer) {
      $edit_button = null;
      $delete_button = null;
      if ($user->getIsAdmin() ||
          ($user->getPHID() == $timer->getAuthorPHID())) {
        $edit_button = phutil_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/edit/'.$timer->getID().'/'
          ),
          'Edit');

        $delete_button = javelin_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href' => '/countdown/delete/'.$timer->getID().'/',
            'sigil' => 'workflow'
          ),
          'Delete');
      }
      $rows[] = array(
        phutil_escape_html($timer->getID()),
        $handles[$timer->getAuthorPHID()]->renderLink(),
        phutil_tag(
          'a',
          array(
            'href' => '/countdown/'.$timer->getID().'/',
          ),
          $timer->getTitle()),
        phabricator_datetime($timer->getDatepoint(), $user),
        $edit_button,
        $delete_button,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Author',
        'Title',
        'End Date',
        '',
        ''
      ));

    $table->setColumnClasses(
      array(
        null,
        null,
        'wide pri',
        null,
        'action',
        'action',
      ));

    $panel = id(new AphrontPanelView())
      ->appendChild($table)
      ->setHeader('Timers')
      ->setCreateButton('Create Timer', '/countdown/edit/')
      ->appendChild($pager);

    return $this->buildStandardPageResponse($panel,
      array(
        'title' => 'Countdown',
      ));
  }
}
