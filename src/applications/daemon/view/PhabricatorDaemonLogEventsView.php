<?php

final class PhabricatorDaemonLogEventsView extends AphrontView {

  private $events;
  private $combinedLog;

  public function setEvents(array $events) {
    assert_instances_of($events, 'PhabricatorDaemonLogEvent');
    $this->events = $events;
    return $this;
  }

  public function setCombinedLog($is_combined) {
    $this->combinedLog = $is_combined;
    return $this;
  }

  public function render() {
    $rows = array();

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    foreach ($this->events as $event) {

      // Limit display log size. If a daemon gets stuck in an output loop this
      // page can be like >100MB if we don't truncate stuff. Try to do cheap
      // line-based truncation first, and fall back to expensive UTF-8 character
      // truncation if that doesn't get things short enough.

      $message = $event->getMessage();

      $more_lines = null;
      $more_chars = null;
      $line_limit = 12;
      if (substr_count($message, "\n") > $line_limit) {
        $message = explode("\n", $message);
        $more_lines = count($message) - $line_limit;
        $message = array_slice($message, 0, $line_limit);
        $message = implode("\n", $message);
      }

      $char_limit = 8192;
      if (strlen($message) > $char_limit) {
        $message = phutil_utf8v($message);
        $more_chars = count($message) - $char_limit;
        $message = array_slice($message, 0, $char_limit);
        $message = implode('', $message);
      }

      $more = null;
      if ($more_chars) {
        $more = number_format($more_chars);
        $more = "\n<... {$more} more characters ...>";
      } else if ($more_lines) {
        $more = number_format($more_lines);
        $more = "\n<... {$more} more lines ...>";
      }

      $row = array(
        phutil_escape_html($event->getLogType()),
        phabricator_date($event->getEpoch(), $this->user),
        phabricator_time($event->getEpoch(), $this->user),
        phutil_escape_html_newlines($message.$more),
      );

      if ($this->combinedLog) {
        array_unshift(
          $row,
          phutil_tag(
            'a',
            array(
              'href' => '/daemon/log/'.$event->getLogID().'/',
            ),
            'Daemon '.$event->getLogID()));
      }

      $rows[] = $row;
    }

    $classes = array(
      '',
      '',
      'right',
      'wide wrap',
    );

    $headers = array(
      'Type',
      'Date',
      'Time',
      'Message',
    );

    if ($this->combinedLog) {
      array_unshift($classes, 'pri');
      array_unshift($headers, 'Daemon');
    }

    $log_table = new AphrontTableView($rows);
    $log_table->setHeaders($headers);
    $log_table->setColumnClasses($classes);

    return $log_table->render();
  }

}
