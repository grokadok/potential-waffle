<?php

namespace bopdev;

trait BopCal
{
    // use \SimpleCalDavClient;

    private $calendarTypes = ['caldav'];

    // !!!!!! BEFORE EACH CHANGE, CHECK MODIFIED !!!!!
    // !!!!!! AFTER EACH CHANGE, SEND NOTIFICATIONS/MODIFICATIONS TO OTHER USERS !!!!!

    /**
     * Adds alarm linked to provided component.
     * @param int $idcomponent
     * @param array $alarm
     */
    private function calAddAlarm(int $idcomponent, array $alarm)
    {
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];
        // trigger
        if (isset($alarm['absolute'])) {
            // absolute
            $request['into'] .= ',trigger_absolute';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['absolute'];
        } else if (isset($alarm['relative'])) {
            // relative
            $request['into'] .= ',trigger_relative';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['relative'];
            // related
            $request['into'] .= ',trigger_related';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $alarm['related'];
        }
        // summary
        if (isset($alarm['summary'])) {
            $request['into'] .= ',summary';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['summary'];
        }
        // description
        if (isset($alarm['description'])) {
            $request['into'] .= ',description';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $this->calAddDescription($alarm['description']);
        }
        // repeat
        if (isset($alarm['repeat'])) {
            $request['into'] .= ',repeat';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $alarm['repeat'];
        }
        // repeat
        if (isset($alarm['duration'])) {
            $request['into'] .= ',duration';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['duration'];
        }
        $this->db->request([
            'query' => 'INSERT INTO cal_alarm (action' . $request['into'] . ') VALUES (?' . $request['values'] . ');',
            'type' => 'ii' . $request['type'],
            'content' => [$idcomponent, $alarm['action'], ...$request['content']],
        ]);
        $idalarm = $this->db->request([
            'query' => 'SELECT LAST_INSERT_ID();',
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO cal_comp_has_alarm (idcal_component, idcal_alarm) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$idcomponent, $idalarm],
        ]);
        return $idalarm;
    }
    /**
     * Adds cal_description if not exists, returns its id.
     * @param string $content Description text.
     */
    private function calAddDescription(string $content)
    {
        if (!isset($this->db->request([
            'query' => 'SELECT idcal_description FROM cal_description WHERE content = ? LIMIT 1;',
            'type' => 's',
            'content' => [$content],
            'array' => true,
        ])[0][0]))
            $this->db->request([
                'query' => 'INSERT INTO cal_description (content) VALUES (?);',
                'type' => 's',
                'content' => [$content],
            ]);
        return $this->db->request([
            'query' => 'SELECT idcal_description FROM cal_description WHERE content = ? LIMIT 1;',
            'type' => 's',
            'content' => [$content],
            'array' => true,
        ])[0][0];
    }
    private function calAddComponent(int $iduser, int $cal_folder, array $component)
    {
        if ($this->calCheckUserWriteAccess($iduser, $cal_folder) === false) return print("Write failure: user $iduser has no write access to cal_folder $cal_folder." . PHP_EOL);
        $uid = $component['uid'] ?? $this->calNewCalFile($iduser, $cal_folder);
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];

        // type
        if (isset($component['type'])) {
            $request['into'] .= ',type';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['type'];
        }
        // description
        if (isset($component['description'])) {
            $request['into'] .= ',description';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $this->calAddDescription($component['description']);
        }
        // all_day
        if (isset($component['all_day'])) {
            $request['into'] .= ',all_day';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['all_day'];
        }
        // class
        if (isset($component['class'])) {
            $request['into'] .= ',class';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['class'];
        }
        // location
        if (isset($component['location'])) {
            $request['into'] .= ',location';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][]  = $this->calAddLocation($component['location']);
        }
        // priority
        if (isset($component['priority'])) {
            $request['into'] .= ',priority';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['priority'];
        }
        // status
        if (isset($component['status'])) {
            $request['into'] .= ',status';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['status'];
        }
        // transparency
        if (isset($component['transparency'])) {
            $request['into'] .= ',transparency';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $component['transparency'];
        }

        $this->db->request([
            'query' => 'INSERT INTO cal_component (uid, created, summary, organizer, start, end' . $request['into'] . ') VALUES (UUID_TO_BIN(?,1), ?, ?, ?, ?, ?' . $request['values'] . ');',
            'type' => 'sssiss' . $request['type'],
            'content' => [$uid, $component['created'], $component['summary'], $iduser, $component['start'], $component['end'], ...$request['content']],
        ]);
        $idcomponent = $this->db->request([
            'query' => 'SELECT MAX(idcal_component) FROM cal_component WHERE uid = (SELECT UUID_TO_BIN(?,1)) AND created = ? LIMIT 1;',
            'type' => 'ss',
            'content' => [$uid, $component['created']],
            'array' => true,
        ])[0][0];

        // recurrence
        if (isset($component['rrule'])) {
            // update component
            $this->db->request([
                'query' => 'UPDATE cal_component SET rrule = 1 WHERE idcal_component = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            // insert rrule
            $this->calComponentSetRRUle($idcomponent, $component['rrule']);
        }
        if (isset($component['rdate'])) {
            $this->db->request([
                'query' => 'UPDATE cal_component SET rdate = 1 WHERE idcal_component = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            // insert rdates
            foreach ($component['rdate'] as $date) {
                $this->db->request([
                    'query' => 'INSERT INTO cal_rdate (idcal_component, date) VALUES (?, ?);',
                    'type' => 'is',
                    'content' => [$idcomponent, $date],
                ]);
            }
        }
        if (isset($component['tag'])) {
            foreach ($component['tag'] as $tag) {
                $this->db->request([
                    'query' => 'INSERT INTO cal_comp_has_tag (idcal_component,idtag) VALUES (?,?);',
                    'type' => 'ii',
                    'content' => [$idcomponent, $tag],
                ]);
            }
        }
        // retrieve & return light info about new event (refactor later to get info directly while creating event)
        return ['event' => $this->calGetEventLightData($idcomponent), 'users' => $this->calGetConnectUsers($cal_folder)];

        // doc for calendar components
        // if (1 === 0) {
        //     // tags ?

        //     // todo: due ? - not compatible with duration property.

        //     // reccuring ?
        //     // recurring: frequency ?
        //     // recurring: interval ?
        //     // recurring: until ?
        //     // recurring: count ?
        //     // recurring: week_start ?
        //     // recurring: by_day ?
        //     // recurring: by_monthday ?
        //     // recurring: by_month ?
        //     // recurring: by_setpos ?
        //     // recurring: exceptions ?
        //     // recurring: exceptions: date
        //     // recurring: exceptions: all_day ?

        //     // alarms ?
        //     // alarms: action
        //     // alarms: trigger absolute ?
        //     // alarms: trigger relative ?
        //     // alarms: trigger related ?
        //     // alarms: summary ? - action=EMAIL: subject.
        //     // alarms: description ? - action=EMAIL: body, action=DISPLAY: text content.
        //     // alarms: repeat ?
        //     // alarms: duration ? - interval between repeats.

        //     // attendees ?
        //     // attendees: attendee
        //     // attendees: delegated from ?
        //     // attendees: delegated to ?
        //     // attendees: sent by ?
        //     // attendees: language ?
        //     // attendees: user type ?
        //     // attendees: role ?
        //     // attendees: status ?
        // }
    }
    private function calAddLocation(string $location)
    {
        $idlocation = $this->db->request([
            'query' => 'SELECT idcal_location FROM cal_location WHERE name = ? LIMIT 1;',
            'type' => 's',
            'content' => [$location],
            'array' => true,
        ])[0][0];
        if (empty($idlocation)) {
            $this->db->request([
                'query' => 'INSERT INTO cal_location (name) VALUES (?);',
                'type' => 's',
                'content' => [$location],
            ]);
            $idlocation = $this->db->request([
                'query' => 'SELECT idcal_location FROM cal_location WHERE name = ?;',
                'type' => 's',
                'content' => [$location],
                'array' => true,
            ])[0][0];
        }
        return $idlocation;
    }
    private function calCheckUserReadAccess(int $iduser, int $cal_folder)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM user_has_calendar WHERE iduser = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $cal_folder],
            'array' => true,
        ]));
    }
    private function calCheckUserWriteAccess(int $iduser, int $cal_folder)
    {
        return $this->db->request([
            'query' => 'SELECT read_only FROM user_has_calendar WHERE iduser = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $cal_folder],
            'array' => true,
        ])[0][0] === 0 ? true : false;
    }
    private function calComponentAddAlarm(int $idcomponent, array $alarm)
    {
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];
        // trigger absolute
        if (isset($alarm['trigabsolute'])) {
            $request['into'] .= ',trigger_absolute';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['triggerabs'];
        } else if (isset($alarm['trigrelative'])) {
            // trigger relative
            $request['into'] .= ',trigger_relative';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['trigrelative'];
            if (isset($alarm['trigrelated'])) {
                // trigger related
                $request['into'] .= ',trigger_related';
                $request['values'] .= ',?';
                $request['type'] .= 'i';
                $request['content'][] = $alarm['trigrelated'];
            }
        }
        // summary - action=EMAIL: subject.
        if (isset($alarm['summary'])) {
            $request['into'] .= ',summary';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['summary'];
        }
        // description - action=EMAIL: body, action=DISPLAY: text content.
        if (isset($alarm['description'])) {
            $iddescription = $this->calAddDescription($alarm['description']);
            $request['into'] .= ',description';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $iddescription;
        }
        // repeat
        if (isset($alarm['repeat'])) {
            $request['into'] .= ',repeat';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $alarm['repeat'];
        }
        // duration - interval between repeats.
        if (isset($alarm['duration'])) {
            $request['into'] .= ',duration';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['duration'];
        }
        $this->db->request([
            'query' => 'INSERT INTO cal_alarm (idcal_component, action' . $request['into'] . ') VALUES (?' . $request['values'] . ');',
            'type' => 'ii' . $request['type'],
            'content' => [$idcomponent, $alarm['action'], ...$request['content']],
        ]);
        return $this->db->request([
            'query' => 'SELECT MAX(idcal_alarm) FROM cal_alarm WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0][0];
    }
    /**
     * Adds attendee to cal component.
     * @param int $idcomponent
     * @param array $attendee
     */
    private function calComponentAddAttendee(int $idcomponent, array $attendee)
    {
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];

        // attendees: delegated from ?
        if (isset($attendee['delegfrom'])) {
            $request['into'] .= ',delegated_from';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['delegfrom'];
        }
        // attendees: delegated to ?
        if (isset($attendee['delegto'])) {
            $request['into'] .= ',delegated_to';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['delegto'];
        }
        // attendees: sent by ?
        if (isset($attendee['sentby'])) {
            $request['into'] .= ',sent_by';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['sentby'];
        }
        // attendees: language ?
        if (isset($attendee['language'])) {
            $request['into'] .= ',language';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['language'];
        }
        // attendees: user type ?
        if (isset($attendee['cutype'])) {
            $request['into'] .= ',cutype';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['cutype'];
        }
        // attendees: role ?
        if (isset($attendee['role'])) {
            $request['into'] .= ',role';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['role'];
        }
        // attendees: status ?
        if (isset($attendee['status'])) {
            $request['into'] .= ',status';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['status'];
        }
        // rsvp
        if (isset($attendee['rsvp'])) {
            $request['into'] .= ',rsvp';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['rsvp'];
        }

        $this->db->request([
            'query' => 'INSERT INTO cal_attendee (idcal_component' . $request['into'] . ') VALUES (?' . $request['values'] . ');',
            'type' => 'i' . $request['type'],
            'content' => [$idcomponent, ...$request['content']],
        ]);
    }
    private function calComponentAddDescription(int $idcomponent, string $description)
    {
        // $this->db->request([
        //     'query' => 'UPDATE cal_component SET description = ? WHERE idcal_component = ? LIMIT 1;',
        //     'type' => 'ii',
        //     'content' => [$this->calAddDescription($description), $idcomponent],
        // ]);
    }
    private function calComponentAddException(int $idcomponent, array $exception)
    {
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];
        if (isset($exception['all_day'])) {
            $request['into'] = ',all_day';
            $request['values'] = ',?';
            $request['type'] = 'i';
            $request['content'][] = $exception['all_day'] ? 1 : 0;
        }
        $this->db->request([
            'query' => 'INSERT INTO cal_exception (idcal_component,date' . $request['into'] . ') VALUES (?,?' . $request['values'] . ');',
            'type' => 'is' . $request['type'],
            'content' => [$idcomponent, $exception['date'], ...$request['content']],
            'array' => true,
        ]);
    }
    private function calComponentAddLocation(int $idcomponent, string $location)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET location = ? WHERE idcal_location = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$this->calAddLocation($location), $idcomponent],
        ]);
    }
    private function calComponentAddRDate(int $idcomponent, string $date)
    {
        $this->db->request([
            'query' => 'INSERT INTO cal_rdate (idcal_component, date) VALUES (?,?);',
            'type' => 'is',
            'content' => [$idcomponent, $date],
        ]);
        $this->db->request([
            'query' => 'UPDATE cal_component SET rdate = 1 WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
    }
    private function calComponentAddTag(int $idcomponent, int $tag)
    {
        $this->db->request([
            'query' => 'INSERT INTO cal_comp_has_tag (idcal_component,idtag) VALUES (?,?);',
            'type' => 'ii',
            'content' => [$idcomponent, $tag],
        ]);
    }
    private function calComponentChangeAlarm(int $idalarm, array $alarm)
    {
        $oldalarm = $this->db->request([
            'query' => 'SELECT action,trigger_absolute,trigger_relative,trigger_related,summary,description,repeat_times,duration FROM cal_alarm WHERE idcal_alarm = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idalarm],
            'array' => false,
        ])[0];
        $request = [
            'set' => [],
            'type' => '',
            'content' => [],
        ];
        // alarms: action
        if (isset($alarm['action']) && $alarm['action'] !== $oldalarm['action']) {
            $request['set'][] = 'action = ?';
            $request['type'] .= 'i';
            $request['content'][] = $alarm['action'];
        }
        // alarms: trigger absolute ?
        if (isset($alarm['trigabsolute']) && $alarm['trigger_absolute'] !== $oldalarm['trigger_absolute']) {
            $request['set'][] = 'trigger_absolute = ?,trigger_relative = NULL,trigger_related = 0';
            $request['type'] .= 's';
            $request['content'][] = $alarm['trigabsolute'];
        } else if (isset($alarm['trigrelative']) && $alarm['trigger_relative'] !== $oldalarm['trigger_relative']) {
            // alarms: trigger relative ?
            $request['set'][] = 'trigger_absolute = NULL,trigger_relative = ?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['trigrelative'];
            if (isset($alarm['trigrelated'])) {
                // alarms: trigger related ?
                $request['set'][] = 'trigger_related = ?';
                $request['type'] .= 'i';
                $request['content'][] = $alarm['trigrelated'] ?? 0;
            }
        }
        // alarms: summary ? - action=EMAIL: subject.
        if (isset($alarm['summary']) && $alarm['summary'] !== $oldalarm['summary']) {
            $request['set'][] = 'summary = ?';
            $request['type'] .= 's';
            $request['content'][] = $alarm['summary'];
        }
        // alarms: description ? - action=EMAIL: body, action=DISPLAY: text content.
        if (isset($alarm['description'])) {
            $iddescription = $this->db->request([
                'query' => 'SELECT description FROM cal_alarm WHERE idcal_alarm = ?;',
                'type' => 'i',
                'content' => [$idalarm],
                'array' => true,
            ])[0][0];
            $newiddescription = $this->calAddDescription($alarm['description']);
            $this->db->request([
                'query' => 'UPDATE cal_alarm SET description = ? WHERE idcal_alarm = ? LIMIT 1;',
                'type' => 'ii',
                'content' => [$newiddescription, $idalarm],
            ]);
            if (!empty($iddescription)) $this->removeUnusedDescription($iddescription);
        }
        // alarms: repeat ?
        if (isset($alarm['repeat'])) {
            $request['set'][] = 'repeat_times = ?';
            $request['type'] .= 'i';
            $request['content'][] = $alarm['repeat'];
            // alarms: duration ? - interval between repeats.
            if (isset($alarm['duration'])) {
                $request['set'][] = 'duration = ?';
                $request['type'] .= 's';
                $request['content'][] = $alarm['duration'];
            }
        } else $request['set'][] = 'duration = NULL';

        if (
            count($request['set']) > 0
        )
            $this->db->request([
                'query' => 'UPDATE cal_alarm SET ' . implode(',', $request['set']) . ' WHERE idcal_alarm = ? LIMIT 1;',
                'type' => $request['type'] . 'i',
                'content' => [...$request['content'], $idalarm],
            ]);
    }
    private function calComponentChangeAttendee(int $idcomponent, array $attendee)
    {
        $request = [
            'set' => [],
            'type' => '',
            'content' => [],
        ];
        // delegated from
        if (isset($attendee['delegfrom'])) {
            $request['set'][] = 'delegated_from = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['delegfrom'];
        }
        // delegated to
        if (isset($attendee['delegto'])) {
            $request['set'][] = 'delegated_to = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['delegto'];
        }
        // sent by
        if (isset($attendee['sentby'])) {
            $request['set'][] = 'sent_by = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['sentby'];
        }
        // language
        if (isset($attendee['language'])) {
            $request['set'][] = 'language = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['language'];
        }
        // user type
        if (isset($attendee['cutype'])) {
            $request['set'][] = 'cutype = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['cutype'];
        }
        // role
        if (isset($attendee['role'])) {
            $request['set'][] = 'role = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['role'];
        }
        // status
        if (isset($attendee['status'])) {
            $request['set'][] = 'status = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['status'];
        }
        // rsvp
        if (isset($attendee['rsvp'])) {
            $request['set'][] = 'rsvp = ?';
            $request['type'] .= 'i';
            $request['content'][] = $attendee['rsvp'];
        }

        $this->db->request([
            'query' => 'UPDATE cal_attendee SET ' . implode(',', $request['set']) . ' WHERE idcal_component = ? AND attendee = ? LIMIT 1;',
            'type' => $request['type'] . 'ii',
            'content' => [...$request['content'], $idcomponent, $attendee['attendee']],
        ]);
    }
    private function calComponentChangeClass(int $idcomponent, int $class)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET class = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$class, $idcomponent],
        ]);
    }
    private function calComponentChangeDescription(int $idcomponent, string $description)
    {
        $iddescription = $this->db->request([
            'query' => 'SELECT description FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'UPDATE cal_component SET description = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$this->calAddDescription($description), $idcomponent],
        ]);
        if (!empty($iddescription)) $this->removeUnusedDescription($iddescription);
    }
    private function calComponentChangeEnd(int $idcomponent, string $end)
    {
        // set new end
        $this->db->request([
            'query' => 'UPDATE cal_component SET end = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$end, $idcomponent],
        ]);
        return $this->db->request([
            'query' => 'SELECT start,end FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0];
    }
    private function calComponentChangeLocation(int $idcomponent, string $location)
    {
        $idlocation = $this->db->request([
            'query' => 'SELECT location FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO cal_location (name) VALUES (?);',
            'type' => 's',
            'content' => [$location],
        ]);
        $newidlocation = $this->db->request([
            'query' => 'SELECT idcal_location FROM cal_location WHERE name = ? LIMIT 1;',
            'type' => 's',
            'content' => [$location],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'UPDATE cal_component SET location = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$newidlocation, $idcomponent],
        ]);
        if (!empty($idlocation)) $this->removeUnusedLocation($idlocation);
    }
    private function calComponentChangePriority(int $idcomponent, int $priority)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET priority = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$priority, $idcomponent],
        ]);
    }
    private function calComponentChangeRRule(int $idcomponent, array $rule)
    {
        $request = [
            'set' => [],
            'type' => '',
            'content' => [],
        ];
        // frequency
        if (isset($rule['frequency'])) {
            $request['set'][] = 'frequency = ?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['frequency'];
        }
        // interval
        if (isset($rule['interval'])) {
            $request['set'][] = 'set_interval = ?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['interval'];
        }
        // until
        if (isset($rule['until'])) {
            $request['set'][] = 'until = ?';
            $request['type'] .= 's';
            $request['content'][] = $rule['until'];
        } else if ($rule['until'] === null) {
            print('until : null' . PHP_EOL);
            $this->db->request([
                'query' => 'UPDATE cal_rrule SET until = NULL WHERE idcal_component = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
        }
        // count
        if (isset($rule['count'])) {
            $request['set'][] = 'count = ?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['count'];
        }
        // week_start
        if (isset($rule['weekstart'])) {
            $request['set'][] = 'week_start = ?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['weekstart'];
        }
        // by_day
        if (isset($rule['by_weekday'])) {
            $request['set'][] = 'by_weekday = ?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_weekday']);
        }
        // by_monthday
        if (isset($rule['by_date'])) {
            $request['set'][] = 'by_date = ?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_date']);
        }
        // by_month
        if (isset($rule['by_month'])) {
            $request['set'][] = 'by_month = ?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_month']);
        }
        // by_setpos
        if (isset($rule['by_setpos'])) {
            $request['set'][] = 'by_setpos = ?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_setpos']);
        }
        if (!empty($request['set']))
            $this->db->request([
                'query' => 'UPDATE cal_rrule SET ' . implode(',', $request['set']) . ' WHERE idcal_component = ?;',
                'type' => $request['type'] . 'i',
                'content' => [...$request['content'], $idcomponent],
            ]);
    }
    private function calComponentChangeStart(int $idcomponent, string $start)
    {
        // set new start
        $this->db->request([
            'query' => 'UPDATE cal_component SET start = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$start, $idcomponent],
        ]);
        return $this->db->request([
            'query' => 'SELECT start,end FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0];
    }
    private function calComponentChangeStatus(int $idcomponent, int $status)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET status = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$status, $idcomponent],
        ]);
    }
    private function calComponentChangeSummary(int $idcomponent, string $summary)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET summary = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$summary, $idcomponent],
        ]);
    }
    private function calComponentChangeTransparency(int $idcomponent, bool $transparency)
    {
        $this->db->request([
            'query' => 'UPDATE cal_component SET transparency = ? WHERE idcal_component = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$transparency, $idcomponent],
        ]);
    }
    private function calComponentRemoveAlarm(int $idalarm)
    {
        $iddescription = $this->db->request([
            'query' => 'SELECT description FROM cal_alarm WHERE idcal_alarm = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idalarm],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'DELETE FROM cal_alarm WHERE idcal_alarm = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idalarm],
        ]);
        if (!empty($iddescription)) $this->removeUnusedDescription($iddescription);
    }
    private function calComponentRemoveAttendee(int $idcomponent, int $attendee)
    {
        $this->db->request([
            'query' => 'DELETE FROM cal_attendee WHERE idcal_component = ? AND attendee = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idcomponent, $attendee],
        ]);
    }
    private function calComponentRemoveDescription(int $idcomponent)
    {
        $iddescription = $this->db->request([
            'query' => 'SELECT description FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0][0];
        if (!empty($iddescription)) {
            $this->db->request([
                'query' => 'UPDATE cal_component SET description = NULL WHERE idcal_component = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            $this->removeUnusedDescription($iddescription);
        }
    }
    private function calComponentRemoveException(int $idcomponent, string $exception)
    {
        $this->db->request([
            'query' => 'DELETE FROM cal_exception WHERE idcal_component = ? AND date = ?;',
            'type' => 'is',
            'content' => [$idcomponent, $exception],
        ]);
    }
    private function calComponentRemoveLocation(int $idcomponent)
    {
        $idlocation = $this->db->request([
            'query' => 'SELECT location FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ])[0][0];
        if (!empty($idlocation)) {
            $this->db->request([
                'query' => 'UPDATE cal_component SET location = NULL WHERE idcal_component = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            $this->removeUnusedLocation($idlocation);
        }
    }
    private function calComponentRemoveRDate(int $idcomponent, string $date)
    {
        $this->db->request([
            'query' => 'DELETE FROM cal_rdate WHERE idcal_component = ? AND date = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$idcomponent, $date],
        ]);
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_rdate WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]))) $this->db->request([
            'query' => 'UPDATE cal_component SET rdate = 0 WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
    }
    private function calComponentRemoveRRUle(int $idcomponent)
    {
        // remove rrule
        $this->db->request([
            'query' => 'DELETE FROM cal_rrule WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // remove exceptions
        $this->db->request([
            'query' => 'DELETE FROM cal_exception WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // increment sequence & set rrule = 0
        // $sequence = $this->db->request([
        //     'query' => 'SELECT sequence FROM cal_component WHERE idcal_component = ? LIMIT 1;',
        //     'type' => 'i',
        //     'content' => [$idcomponent],
        //     'array' => true,
        // ])[0][0];
        // $this->db->request([
        //     'query' => 'UPDATE cal_component SET rrule = 0, sequence = ? WHERE idcal_component = ?;',
        //     'type' => 'ii',
        //     'content' => [++$sequence, $idcomponent],
        // ]);
    }
    private function calComponentRemoveTag(int $idcomponent, int $tag)
    {
        $this->db->request([
            'query' => 'DELETE FROM cal_comp_has_tag WHERE idcal_component = ? AND idtag = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idcomponent, $tag],
        ]);
    }
    /**
     * Sets or update recurrence rule to component.
     * @param int $idcomponent
     * @param array $rule
     */
    private function calComponentSetRRUle(int $idcomponent, array $rule)
    {

        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => []
        ];
        // frequency
        if (isset($rule['frequency'])) {
            $request['into'] .= ',frequency';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['frequency'];
        }
        // interval
        if (isset($rule['interval'])) {
            $request['into'] .= ',set_interval';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['interval'];
        }
        // until
        if (isset($rule['until'])) {
            $request['into'] .= ',until';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = $rule['until'];
        }
        // count
        if (isset($rule['count'])) {
            $request['into'] .= ',count';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['count'];
        }
        // week_start
        if (isset($rule['week_start'])) {
            $request['into'] .= ',week_start';
            $request['values'] .= ',?';
            $request['type'] .= 'i';
            $request['content'][] = $rule['week_start'];
        }
        // by_day
        if (isset($rule['by_weekday'])) {
            $request['into'] .= ',by_weekday';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_weekday']);
        }
        // by_monthday
        if (isset($rule['by_date'])) {
            $request['into'] .= ',by_date';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_date']);
        }
        // by_month
        if (isset($rule['by_month'])) {
            $request['into'] .= ',by_month';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_month']);
        }
        // by_setpos
        if (isset($rule['by_setpos'])) {
            $request['into'] .= ',by_setpos';
            $request['values'] .= ',?';
            $request['type'] .= 's';
            $request['content'][] = implode(',', $rule['by_setpos']);
        }
        return $this->db->request([
            'query' => 'INSERT INTO cal_rrule (idcal_component' . $request['into'] . ') VALUES (?' . $request['values'] . ');',
            'type' => 'i' . $request['type'],
            'content' => [$idcomponent, ...$request['content']],
        ]);
    }
    private function calComponentUpdate(int $cal_folder, int $idcomponent, array $update)
    {
        $request = [
            'set' => [],
            'type' => '',
            'content' => [],
        ];
        // type
        if (isset($update['type'])) {
            $request['set'][] = 'type = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['type'] ?? 0;
        }
        // summary
        if (isset($update['summary'])) {
            $request['set'][] = 'summary = ?';
            $request['type'] .= 's';
            $request['content'][] = $update['summary'];
        }
        // start
        if (!empty($update['start'])) {
            $request['set'][] = 'start = ?';
            $request['type'] .= 's';
            $request['content'][] = $update['start'];
        }
        // end
        if (!empty($update['end'])) {
            $request['set'][] = 'end = ?';
            $request['type'] .= 's';
            $request['content'][] = $update['end'];
        }
        // description
        if (isset($update['description'])) {
            if (!empty($update['description'])) {
                $request['set'][] = 'description = ?';
                $request['type'] .= 'i';
                $request['content'][] = $this->calAddDescription($update['description']);
            } else $this->calComponentRemoveDescription($idcomponent);
        }
        // all_day
        if (isset($update['all_day'])) {
            $request['set'][] = 'all_day = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['all_day'];
        }
        // class
        if (!empty($update['class'])) {
            $request['set'][] = 'class = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['class'];
        }
        // location
        if (isset($update['location'])) {
            if (!empty($update['location'])) {
                $request['set'][] = 'location = ?';
                $request['type'] .= 'i';
                $request['content'][]  = $this->calAddLocation($update['location']);
            } else $this->calComponentRemoveLocation($idcomponent);
        }
        // priority
        if (isset($update['priority'])) {
            $request['set'][] = 'priority = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['priority'];
        }
        // status
        if (isset($update['status'])) {
            $request['set'][] = 'status = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['status'];
        }
        // transparency
        if (isset($update['transparency'])) {
            $request['set'][] = 'transparency = ?';
            $request['type'] .= 'i';
            $request['content'][] = $update['transparency'];
        }
        // recurrence
        if (isset($update['rrule'])) {
            $request['set'][] = 'rrule = ?';
            $request['type'] .= 'i';
            $ruleset = !empty($this->db->request([
                'query' => 'SELECT NULL FROM cal_rrule WHERE idcal_component = ?;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]));
            if (!empty($update['rrule'])) {
                $request['content'][] = 1;
                // insert/update rrule
                $ruleset ?
                    $this->calComponentChangeRRUle($idcomponent, $update['rrule'])
                    : $this->calComponentSetRRUle($idcomponent, $update['rrule']);
            } else {
                $request['content'][] = 0;
                // delete rrule
                if ($ruleset)
                    $this->calComponentRemoveRRUle($idcomponent);
            }
        }
        if (isset($update['rdate'])) {
            $request['set'][] = 'rdate = ?';
            $request['type'] .= 'i';
            // remove rdates
            $this->db->request([
                'query' => 'DELETE FROM cal_rdate WHERE idcal_component = ?;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            if (!empty($update['rdate'])) {
                $request['content'][] = 1;
                // insert rdates
                foreach ($update['rdate'] as $date) {
                    $this->db->request([
                        'query' => 'INSERT INTO cal_rdate (idcal_component, date) VALUES (?, ?);',
                        'type' => 'is',
                        'content' => [$idcomponent, $date],
                    ]);
                }
            } else {
                $request['content'][] = 0;
            }
        }

        $set = implode(',', $request['set']);
        if (!empty($request['set']))
            $this->db->request([
                'query' => "UPDATE cal_component SET $set WHERE idcal_component = ?;",
                'type' => $request['type'] . 'i',
                'content' => [...$request['content'], $idcomponent],
            ]);

        if (isset($update['tag'])) {
            $this->db->request([
                'query' => 'DELETE FROM cal_comp_has_tag WHERE idcal_component = ?;',
                'type' => 'i',
                'content' => [$idcomponent],
            ]);
            if (!empty($update['tag']))
                foreach ($update['tag'] as $tag) {
                    $this->db->request([
                        'query' => 'INSERT INTO cal_comp_has_tag (idcal_component,idtag) VALUES (?,?);',
                        'type' => 'ii',
                        'content' => [$idcomponent, $tag],
                    ]);
                }
        }
        // retrieve & return light info about updated event
        $light = $this->calGetEventLightData($idcomponent);
        return ['event' => $light, 'users' => $this->calGetConnectUsers($cal_folder, $light['start'], $light['end'])];
    }
    private function calFileCheckModified(string $uid, string $modified)
    {
        return !empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_file WHERE uid = UUID_TO_BIN(?,1) AND modified = ? LIMIT 1;',
            'type' => 'ss',
            'content' => [$uid, $modified],
        ]));
    }
    /**
     * Returns connected users linked to cal_folder on specified range.
     */
    private function calGetConnectUsers(int $cal_folder, string $start = null, string $end = null)
    {
        $users = [];
        foreach ($this->db->request([
            'query' => 'SELECT idsession,iduser,fd FROM session;',
        ]) as $user) $users[] = [...$user, 'inrange' => isset($start) && isset($end) ? !empty($this->db->request([
            'query' => 'SELECT NULL FROM session_has_calendar WHERE idsession = ? AND idcal_folder = ? AND start < ? AND end > ?;',
            'type' => 'iiss',
            'content' => [$user['idsession'], $cal_folder, $end, $start],
        ])) : null];
        return $users;
    }
    private function calGetDescriptions(array $iddecriptions)
    {
        $result = [];
        $in = implode(',', $iddecriptions);
        $res = $this->db->request([
            'query' => "SELECT idcal_description,content FROM cal_description WHERE idcal_description IN $in;",
            'array' => true,
        ]);
        foreach ($res as $description) $result[$description[0]] = $description[1];
        return $result;
    }
    private function calGetEventAlarms(int $idcomponent)
    {
        $alarms = [];
        foreach ($this->db->request([
            'query' => 'SELECT idcal_alarm,action,trigger_absolute,trigger_relative,trigger_related,summary,description,repeat_times,duration FROM cal_alarm WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]) as $alarm) $alarms[$alarm['idcal_alarm']] = $alarm;

        return !empty($alarms) ? $alarms : null;
    }
    private function calGetEventAttendees(int $idcomponent)
    {
        return
            $this->db->request([
                'query' => 'SELECT attendee, delegated_from, delegated_to, language, sent_by, cutype, role, status, rsvp FROM cal_attendee WHERE idcal_component = ?;',
                'type' => 'i',
                'content' => [$idcomponent],
                'array' => false,
            ]);
    }
    /**
     * Gets event(s) for given uid.
     * @param int $uid Duh.
     */
    private function calGetEventData(int $idcomponent)
    {
        $event = $this->db->request([
            'query' => 'SELECT created, modified, summary, description, organizer, timezone, start, end, all_day, class, location, priority, status, transparency, sequence, rrule, rdate, recur_id, thisandfuture
            FROM cal_component
            WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => false,
        ])[0];
        $response = [
            'descriptions' => [],
            'event' => [],
            'languages' => [],
            'tags' => [],
            'timezones' => [],
            'users' => [],
        ];

        // get description
        addIfNotNullNorExists($event['description'], $response['descriptions']);

        // get organizer
        addIfNotNullNorExists($event['organizer'], $response['users']);

        // get timezone
        addIfNotNullNorExists($event['timezone'], $response['timezones']);

        // get location
        addIfNotNullNorExists($event['location'], $response['locations']);

        // get recurrence
        if ($event['rrule'])
            $event['recurrence']['rule'] = $this->calGetEventRRule($idcomponent);
        if ($event['rdate'])
            $event['recurrence']['date'] = $this->calGetEventRDate($idcomponent);
        // get exceptions
        if (isset($event['recurrence']))
            $event['recurrence']['exceptions'] = $this->calGetEventException($idcomponent);

        // get attendees
        $event['attendees'] = $this->calGetEventAttendees($idcomponent);
        foreach ($event['attendees'] as $attendee) {
            foreach ([$attendee['attendee'], $attendee['delegated_from'], $attendee['delegated_to'], $attendee['sent_by']] as $value)
                addIfNotNullNorExists($response['users'], $value);
            addIfNotNullNorExists($attendee['idlanguage'], $response['languages']);
        }

        // get alarms
        $event['alarms'] = $this->calGetEventAlarms($idcomponent);
        foreach ($event['alarms'] as $alarm) addIfNotNullNorExists($alarm['description'], $response['descriptions']);

        // get tags
        $event['tags'] = $this->calGetEventTagId($idcomponent);
        foreach ($event['tags'] as $tag) addIfNotNullNorExists($tag[0], $response['tags']);

        $response['event'][$event['idcal_component']] = $event;

        // get content
        // descriptions
        if (!empty($response['descriptions']))
            $response['descriptions'] = $this->calGetDescriptions($response['descriptions']);
        // users
        if (!empty($response['users'])) $response['users'] = $this->calGetUsers($response['users']);
        // timezones
        if (!empty($response['timezones'])) $response['timezones'] = $this->calGetTimeZones($response['timezones']);
        // tags
        if (!empty($response['tags'])) $response['tags'] = $this->calGetTags($response['tags']);
        // locations
        if (!empty($response['locations'])) $response['locations'] = $this->calGetLocations($response['locations']);

        return $response;
    }
    private function calGetEventException(int $idcomponent)
    {
        $exceptions = [];
        foreach ($this->db->request([
            'query' => 'SELECT date FROM cal_exception WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ]) as $exception) $exceptions[] = $exception[0];
        return $exceptions;
    }
    private function calGetEventLightData(int $idcomponent)
    {
        $event = $this->db->request([
            'query' => "SELECT idcal_component,BIN_TO_UUID(uid,1) as uid,type,summary,start,end,all_day,transparency,sequence,rrule,rdate,recur_id,thisandfuture FROM cal_component WHERE idcal_component = ? LIMIT 1;",
            'type' => 'i',
            'content' => [$idcomponent],
        ])[0];
        $event['modified'] = $this->db->request([
            'query' => 'SELECT modified FROM cal_file WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$event['uid']],
            'array' => true,
        ])[0][0];
        // if rrule, get rrule
        $event['rrule'] = !empty($event['rrule']) ? $this->calGetEventRRule($event['idcal_component']) : null;
        // if rdate, get rdate
        $event['rdate'] = !empty($event['rdate']) ? $this->calGetEventRDate($event['idcal_component']) : null;
        // if exceptions, get'em
        if (!empty($event['rrule']) || !empty($event['rdate']))
            $event['exceptions'] = $this->calGetEventException($event['idcal_component']);
        // alarms
        // !!! GET ALARMS WHERE ACTION NOT EMAIL NOR SOUND FOR APP, ONLY DISPLAY
        $event['alarms'] = $this->calGetEventAlarms($idcomponent);
        return $event;
    }
    private function calGetEventRDate(int $idcomponent)
    {
        $rdates = [];
        foreach ($this->db->request([
            'query' => 'SELECT date FROM cal_rdate WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ]) as $date) $rdates[] = $date[0];
        return $rdates;
    }
    private function calGetEventRRule(int $idcomponent)
    {
        return $this->db->request([
            'query' =>
            'SELECT frequency,set_interval as "interval",until,count,week_start,by_weekday,by_date,by_month,by_setpos FROM cal_rrule WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ])[0];
    }
    private function calGetEventTagId(int $idcomponent)
    {
        $tagIds = [];
        $tags = $this->db->request([
            'query' => 'SELECT idtag FROM cal_comp_has_tag WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
            'array' => true,
        ]);
        foreach ($tags as $tag) $tagIds[] = $tag[0];
        return $tagIds;
    }
    private function calGetEventsInRange(int $iduser, string $start, string $end)
    {
        // response = idcal_folder[idcal_component[]]
        $response = [];
        // foreach cal_folder, get events
        foreach ($this->db->request([
            'query' => 'SELECT idcal_folder FROM user_has_calendar WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $idcal) {
            $response[$idcal[0]] = $this->db->request([
                'query' => "SELECT BIN_TO_UUID(uid,1) as uid,idcal_component,(SELECT modified FROM cal_file WHERE cal_file.uid = cal_component.uid LIMIT 1) as modified,type,summary,start,end,all_day,transparency,sequence,rrule,rdate,recur_id,thisandfuture FROM cal_component WHERE uid IN (SELECT uid FROM cal_file WHERE idcal_folder = ?) AND start < ? AND end > ?;",
                'type' => 'iss',
                'content' => [$idcal[0], $end, $start],
            ]);
            // if rrule, get rrule
            foreach ($response[$idcal[0]] as $key => $value) {
                $response[$idcal[0]][$key]['rrule'] = $value['rrule'] ? $this->calGetEventRRule($value['idcal_component']) : null;
                // if rdate, get rdate
                $response[$idcal[0]][$key]['rdates'] = $value['rdate'] ? $this->calGetEventRDate($value['idcal_component']) : null;
                // if exceptions, get'em
                if ($value['rrule'] || $value['rdate'])
                    $response[$idcal[0]][$key]['exceptions'] = $this->calGetEventException($value['idcal_component']);
                // alarms
                // !!! GET ALARMS WHERE ACTION NOT EMAIL NOR SOUND FOR APP, ONLY DISPLAY
                $response[$idcal[0]][$key]['alarms'] = $this->calGetEventAlarms($value['idcal_component']);
            }
        }
        return $response;
    }
    private function calGetLocations(array $locationids)
    {
        $response = [];
        $in = implode(',', $locationids);
        $res = $this->db->request([
            'query' => "SELECT idcal_location,name FROM cal_locations WHERE idcal_location IN $in;",
            'array' => true,
        ]);
        foreach ($res as $location) $response[$location[0]] = $location[1];
        return $response;
    }
    private function calGetStaticValues()
    {
        $response = [
            'status' => [],
            'cutype' => [],
            'role' => [],
            'frequency' => [],
            'action' => [],
            'class' => [],
        ];
        // get statuses
        foreach ($this->db->request([
            'query' => 'SELECT idcal_status,name FROM cal_status;',
        ]) as $status) $response['status'][$status['idcal_status']] = $status['name'];
        // get cutypes
        foreach ($this->db->request([
            'query' => 'SELECT idcal_cutype,name FROM cal_cutype;',
        ]) as $cutype) $response['cutype'][$cutype['idcal_cutype']] = $cutype['name'];
        // get roles
        foreach ($this->db->request([
            'query' => 'SELECT idcal_role, name FROM cal_role;',
        ]) as $role) $response['role'][$role['idcal_role']] = $role['name'];
        // get frequencies
        foreach ($this->db->request([
            'query' => 'SELECT idcal_frequency,name FROM cal_frequency;',
        ]) as $frequency) $response['frequency'][$frequency['idcal_frequency']] = $frequency['name'];
        // get actions
        foreach ($this->db->request([
            'query' => 'SELECT idcal_action,name FROM cal_action;',
        ]) as $action) $response['action'][$action['idcal_action']] = $action['name'];
        // get classes
        foreach ($this->db->request([
            'query' => 'SELECT idcal_class,name FROM cal_class;',
        ]) as $class) $response['class'][$class['idcal_class']] = $class['name'];
        return $response;
    }
    private function calGetTags(array $tagids)
    {
        $response = [];
        $in = implode(',', $tagids);
        $res = $this->db->request([
            'query' => "SELECT idtag,name FROM tag WHERE idtag IN $in;",
            'array' => true,
        ]);
        foreach ($res as $tag) $response[$tag[0]] = $tag[1];
        return $response;
    }
    private function calGetTimeZones(array $timezones)
    {
        $response = [];
        $in = implode(',', $timezones);
        $res = $this->db->request([
            'query' => "SELECT idtimezone,name,offset FROM timezone WHERE idtimezone IN $in;",
            'array' => true,
        ]);
        foreach ($res as $tz) $response[$tz[0]] = ['name' => $tz[1], 'offset' => $tz[2]];
        return $response;
    }
    /**
     * Returns all cal_folders user has access to.
     */
    private function calGetUserCalendars(int $iduser)
    {
        $folders = [];
        foreach ($this->db->request([
            'query' => 'SELECT idcal_folder,read_only,color,visible FROM user_has_calendar WHERE iduser = ?;',
            'type' => 'i',
            'content' => [$iduser],
            'array' => true,
        ]) as $folder)
            $folders["$folder[0]"] = [
                'name' => $this->db->request([
                    'query' => 'SELECT name FROM cal_folder WHERE idcal_folder = ? LIMIT 1;',
                    'type' => 'i',
                    'content' => [$folder[0]],
                    'array' => true,
                ])[0][0],
                'read_only' => !empty($folder[1]),
                'owner' => !empty($this->db->request([
                    'query' => 'SELECT NULL FROM cal_folder WHERE idcal_folder = ? AND owner = ? LIMIT 1;',
                    'type' => 'ii',
                    'content' => [$folder[0], $iduser],
                ])),
                'color' => !empty($folder[2]) ? $folder[2] : null,
                'visible' => !empty($folder[3]),
            ];
        return $folders;

        // foreach ($cal_folders as $key => $value) $cal_folders[$key]['description'] = $this->db->request([
        //     'query' => 'SELECT content FROM cal_description WHERE idcal_description = ? LIMIT 1;',
        //     'type' => 'i',
        //     'content' => [$value['description']],
        //     'array' => true,
        // ])[0][0];
        // return $cal_folders;
    }
    private function calGetUsers(array $userids)
    {
        $response = ['users' => [], 'roles' => []];
        $in = implode(',', $userids);
        foreach ($this->db->request([
            'query' => "SELECT iduser,first_name,last_name FROM user WHERE iduser IN $in;",
            'array' => true,
        ]) as $user) $response['users'][$user[0]] = ['first_name' => $user[1], 'last_name' => $user[2]];
        foreach ($this->db->request([
            'query' => "SELECT iduser,idrole FROM user_has_role WHERE iduser IN $in;",
            'array' => true,
        ]) as $role) {
            $response['users'][$role[0]]['role'] = $role[1];
            addIfNotNullNorExists($role[1], $response['roles']);
        }
        $in = implode(',', $response['roles']);
        $response['roles'] = [];
        foreach ($this->db->request([
            'query' => "SELECT idrole,name,short FROM role WHERE idrole IN $in;",
            'array' => true,
        ]) as $role) $response['roles'][$role[0]] = ['name' => $role[1], 'short' => $role[2]];
        return $response;
    }
    /**
     * Creates a new calendar file.
     * @param array $folder
     */
    private function calNewCalFile(int $iduser, int $cal_folder)
    {
        if (!$this->calCheckUserWriteAccess($iduser, $cal_folder))
            return print("Write failure: user $iduser has no write access to cal_folder $cal_folder." . PHP_EOL);
        $uuid = $this->db->request([
            'query' => 'SELECT uuid();',
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO cal_file (uid, idcal_folder) VALUES (UUID_TO_BIN(?,1), ?);',
            'type' => 'si',
            'content' => [$uuid, $cal_folder],
        ]);
        return $uuid;
    }
    /**
     * Creates a new cal folder.
     * @param int $iduser User id to link the folder to.
     * @param array $folder Associative array containing name and optionnal description.
     * @param string $folder['name'] Calendar's name.
     * @param string $folder['description] Optionnal folder description.
     */
    private function calNewCalFolder(int $iduser, array $folder)
    {
        if (!empty($this->db->request([
            'query' => 'SELECT NULL FROM user_has_calendar LEFT JOIN cal_folder USING (idcal_folder) WHERE iduser = ? AND name = ? LIMIT 1;',
            'type' => 'is',
            'content' => [$iduser, $folder['name']],
        ]))) return ['fail' => 'Calendar already exists!', 'message' => 'Ce calendrier existe déjà, vous devez choisir un autre nom.'];
        $request = [
            'into' => '',
            'values' => '',
            'type' => '',
            'content' => [],
        ];
        if (!empty($folder['description'])) {
            $request['into'] = ',description';
            $request['values'] = ',?';
            $request['type'] = 'i';
            $request['content'][] = [$this->calAddDescription($folder['description'])];
        }
        $this->db->request([
            'query' => 'INSERT INTO cal_folder (name, owner' . $request['into'] . ') VALUES (?,?' . $request['values'] . ');',
            'type' => 'si' . $request['type'],
            'content' => [$folder['name'], $iduser, ...$request['content']],
        ]);
        $idcal = $this->db->request([
            'query' => 'SELECT MAX(idcal_folder) FROM cal_folder WHERE name = ? AND owner = ? LIMIT 1;',
            'type' => 'si',
            'content' => [$folder['name'], $iduser],
            'array' => true,
        ])[0][0];
        $this->db->request([
            'query' => 'INSERT INTO user_has_calendar (iduser,idcal_folder,color) VALUES (?,?,?);',
            'type' => 'iis',
            'content' => [$iduser, $idcal, $folder['color'] ?? ''],
        ]);
        return [
            'id' => $idcal,
            'name' => $folder['name'],
            'description' => $folder['description'] ?? '',
            'owner' => true,
            'read_only' => false,
            'visible' => true,
        ];
    }
    private function calRemoveComponent(int $idcomponent)
    {
        // tag
        $this->db->request([
            'query' => 'DELETE FROM cal_comp_has_tag WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // attachment
        $this->db->request([
            'query' => 'DELETE FROM cal_attachment WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // rrule
        $this->db->request([
            'query' => 'DELETE FROM cal_rrule WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // rdate
        $this->db->request([
            'query' => 'DELETE FROM cal_rdate WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // exception
        $this->db->request([
            'query' => 'DELETE FROM cal_exception WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // alarm
        $this->db->request([
            'query' => 'DELETE FROM cal_alarm WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // attendee
        // $attendees = $this->db->request([
        //     'query' => 'SELECT idcal_attendee FROM cal_attendee WHERE idcal_component = ?;',
        //     'type' => 'i',
        //     'content' => [$idcomponent],
        //     'array' => true,
        // ]);
        // if attendee with status confirmed, ask user if he wants to send them intel that event is aborted.
        // There will be notification anyway.
        $this->db->request([
            'query' => 'DELETE FROM cal_attendee WHERE idcal_component = ?;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // component
        $component = $this->db->request([
            'query' => 'SELECT BIN_TO_UUID(uid,1) AS uuid,location,description,start,end FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ])[0];
        $this->db->request([
            'query' => 'DELETE FROM cal_component WHERE idcal_component = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idcomponent],
        ]);
        // remove location
        if (!empty($component['location'])) $this->removeUnusedLocation($component['location']);
        // remove description
        if (!empty($component['description'])) $this->removeUnusedDescription($component['description']);
        // file if empty
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_component WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$component['uuid']],
            'array' => true,
        ]))) $this->db->request([
            'query' => 'DELETE FROM cal_file WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$component['uuid']],
        ]);

        // send intel to users having access to calendar of this new turn of events.

        // return start/end of deleted component
        return [$component['start'], $component['end']];
    }
    private function calRemoveFile(string $uid)
    {
        $components = $this->db->request([
            'query' => 'SELECT idcal_component FROM cal_component WHERE uid = UUID_TO_BIN(?,1);',
            'type' => 's',
            'content' => [$uid],
            'array' => true,
        ]);
        // if components, removes components (last one removes also file)
        if (!empty($components))
            foreach ($components as $component) $this->removeComponent($component[0]);
        // else remove file
        else $this->db->request([
            'query' => 'DELETE FROM cal_file WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$uid],
        ]);
    }
    private function calRemoveFolder(int $iduser, int $cal_folder)
    {
        // if user is owner
        if (!empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_folder WHERE idcal_folder = ? AND owner = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$cal_folder, $iduser],
        ]))) {
            $users = [];
            foreach ($this->db->request([
                'query' => 'SELECT iduser FROM user_has_calendar WHERE idcal_folder = ?;',
                'type' => 'i',
                'content' => [$cal_folder],
                'array' => true,
            ]) as $user) $users[] = $user[0];
            $users = implode(',', $users);
            $fds = [];
            foreach ($this->db->request([
                'query' => "SELECT fd FROM session WHERE iduser IN ($users);",
                'array' => true,
            ]) as $fd) $fds[] = $fd[0];
            $this->db->request([
                'query' => 'DELETE FROM cal_folder WHERE idcal_folder = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$cal_folder],
                'array' => true,
            ]);
            return $fds;
        }
        return false;
    }
    private function calRemoveFromUser(int $iduser, int $cal_folder)
    {
        $this->db->request([
            'query' => 'DELETE FROM user_has_calendar WHERE iduser = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$iduser, $cal_folder],
        ]);
    }
    /**
     * Check if a specific description is unused to remove it.
     */
    private function calRemoveUnusedDescription(int $iddescription)
    {
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_folder WHERE description = ? UNION SELECT NULL FROM cal_component WHERE description = ? UNION SELECT NULL FROM cal_alarm WHERE description = ? LIMIT 1;',
            'type' => 'iii',
            'content' => [$iddescription, $iddescription, $iddescription],
            'array' => true,
        ]))) $this->db->request([
            'query' => 'DELETE FROM cal_description WHERE idcal_description = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$iddescription],
        ]);
    }
    /**
     * Checks all descriptions for unused ones to remove them.
     */
    private function calRemoveUnusedDescriptions()
    {
        foreach ($this->db->request([
            'query' => 'SELECT idcal_description FROM cal_description;',
            'array' => true,
        ]) as $iddescri) {
            if (empty($this->db->request([
                'query' => 'SELECT NULL FROM cal_component WHERE description = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$iddescri],
            ])) && empty($this->db->request([
                'query' => 'SELECT NULL FROM cal_folder WHERE decription = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$iddescri],
            ])) && empty($this->db->request([
                'query' => 'SELECT NULL FROM cal_alarm WHERE description = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$iddescri],
            ]))) $this->db->request([
                'query' => 'DELETE FROM cal_description WHERE idcal_description = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$iddescri],
            ]);
        }
    }
    /**
     * Check if a specific location is unused to remove it.
     */
    private function calRemoveUnusedLocation(int $idlocation)
    {
        if (empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_component WHERE idcal_location = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idlocation],
            'array' => true,
        ])))
            $this->db->request([
                'query' => 'DELETE FROM cal_location WHERE idcal_location = ? LIMIT 1;',
                'type' => 'i',
                'content' => [$idlocation],
            ]);
    }
    /**
     * Checks all locations for unused ones to remove them.
     */
    private function calRemoveUnusedLocations()
    {
        foreach ($this->db->request([
            'query' => 'SELECT idcal_location FROM cal_location;',
            'array' => true,
        ]) as $idloc) if (empty($this->db->request([
            'query' => 'SELECT NULL FROM cal_component WHERE location = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idloc],
        ]))) $this->db->request([
            'query' => 'DELETE FROM cal_location WHERE idcal_location = ? LIMIT 1;',
            'type' => 'i',
            'content' => [$idloc],
        ]);
    }
    /**
     * @return array [ int $idcal_folder, string $modified ]
     */
    private function calSetFileModified(string $uid)
    {
        $this->db->request([
            'query' => 'UPDATE cal_file SET modified = CURRENT_TIMESTAMP(6) WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$uid],
        ]);
        return $this->db->request([
            'query' => 'SELECT idcal_folder,modified FROM cal_file WHERE uid = UUID_TO_BIN(?,1) LIMIT 1;',
            'type' => 's',
            'content' => [$uid],
            'array' => true
        ])[0];
    }
    private function calSetFolderColor(int $iduser, int $cal_folder, string $color)
    {
        $this->db->request([
            'query' => 'UPDATE user_has_calendar SET color = ? WHERE iduser = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'sii',
            'content' => [$color, $iduser, $cal_folder],
        ]);
    }
    private function calSetSession(int $idsession, int $cal_folder, string $start, string $end)
    {
        $cal = $this->db->request([
            'query' => 'SELECT start,end FROM session_has_calendar WHERE idsession = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'ii',
            'content' => [$idsession, $cal_folder],
        ]);
        if (empty($cal))
            return $this->db->request([
                'query' => 'INSERT INTO session_has_calendar (idsession,idcal_folder,start,end) VALUES (?,?,?,?);',
                'type' => 'iiss',
                'content' => [$idsession, $cal_folder, $start, $end],
            ]);
        else if ($cal['start'] !== $start || $cal['end'] !== $end)
            return $this->db->request([
                'query' => 'UPDATE session_has_calendar SET start = ? AND end = ? WHERE idsession = ? AND idcal_folder = ? LIMIT 1;',
                'type' => 'ssii',
                'content' => [$start, $end, $idsession, $cal_folder],
            ]);
        return;
    }
    private function calSetVisibility(int $iduser, int $cal_folder, int $visible)
    {
        return $this->db->request([
            'query' => 'UPDATE user_has_calendar SET visible = ? WHERE iduser = ? AND idcal_folder = ? LIMIT 1;',
            'type' => 'iii',
            'content' => [$visible, $iduser, $cal_folder],
        ]);
    }




    /**
     * Get external calendars of all or selected types linked to a user.
     * @param int $iduser User to get calendars from.
     * @param null|string[] $types If set, types of calendar to get, all by default.
     * 
     * @return false|array
     */
    private function calGetExternalCals(int $iduser, ?array $types)
    {
        $response = [];
        // for each type of calendar, check if user has some.
        foreach ($types ?? $this->calendarTypes as $type) {
            if ($type === 'caldav') {
                // $response['caldav']=[];
                $res = $this->db->request([
                    'query' => 'SELECT idcaldav,role FROM user_has_caldav WHERE iduser = ?;',
                    'type' => 'i',
                    'content' => [$iduser],
                    'array' => false,
                ]);
                if (isset($res[0])) {
                    foreach ($res as $cal) {
                        $response['caldav'][] = [
                            'id' => $cal['idcaldav'],
                            'role' => $cal['role'],
                        ];
                    }
                }
            }
        }
        return empty($response) ? false : $response;
    }
    /**
     * Get events from specific caldav on selected period of time;
     */
    private function calGetEventFromCaldav(int $id, array $period)
    {
        $res = $this->db->request([
            'query' => 'SELECT url,user,pass FROM caldav WHERE idcaldav = ?;',
            'type' => 'i',
            'content' => [$id],
            'array' => false,
        ]);
        if ($res) {
            // simplecaldav getevent()
        }
    }
}
