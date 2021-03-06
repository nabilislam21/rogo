<?php
    include_once($BASE_PATH . 'common/DatabaseException.php');

    function insertUser($username, $email, $pass_hash) {
        global $db;
        $errors = new DatabaseException();
        $followableid;

        if(!validateUsername($username)) {
            $errors->addError('username', 'invalid');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO followable (type) VALUES (1)");
            $stmt->execute();
            $followableid = $db->lastInsertId('followable_followableid_seq');
        } catch (Exception $e) {
            $db->rollBack();
            $errors->addError('followable', 'error processing insert into followable table');
            $errors->addError('exception', $e->getMessage());
            throw ($errors);
        }

        try {
            $stmt = $db->prepare("INSERT INTO rogouser (userid, fullname, username, email, passhash, birthdate, registrationdate, lastaccess, location, reputation, credits, viewcount, downvotes, upvotes, permissiontype, websiteurl, aboutme, consecutiveaccessdays) VALUES ($followableid, 'Rogo', ?, ?, ?, now(), now(), now(), 'Earth', 0, 0, 0, 0, 0, 1, null, null, 1)");
            $stmt->execute(array($username, $email, $pass_hash));
        } catch (Exception $e) {
            $db->rollBack();
            $errors->addError('rogouser', 'error processing insert into rogouser table');
            $errors->addError('exception', $e->getMessage());
            throw ($errors);
        }
        $db->commit();
        return $followableid;
    }

    function getNumberOfUsersWithSorting($sort) {
        global $db;
        $query = "SELECT count(*) AS total FROM rogouser ";
        $now = date('Y-m-d', time()-1296000); // current date minus 15 days

        switch ($sort) {
            case 'reputation':
            case 'active':
            case 'voters':
            case 'popular':
                break;
            case 'new':
                $query = $query."WHERE registrationdate > ?";
                break;
            default:
                throw new Exception("getUsersWithSorting: Invalid sorting");
                break;
        }
        $stmt = $db->prepare($query);
        if($sort == 'new')
            $stmt->execute(array($now));
        else
            $stmt->execute();
        return $stmt->fetch();
    }

    function getUsersWithSorting($sort, $limit, $offset) {
        global $db;
        $query = "SELECT userid, username, email, registrationdate, reputation, lastaccess, upvotes, downvotes, viewcount FROM rogouser ";
        $now = date('Y-m-d', time()-1296000); // current date minus 15 days

        switch ($sort) {
            case 'reputation':
                $query = $query."ORDER BY reputation DESC, registrationdate, username";
                break;
            case 'new':
                $query = $query."WHERE registrationdate > ? ORDER BY registrationdate DESC, username";
                break;
            case 'active':
                $query = $query."ORDER BY lastaccess DESC";
                break;
            case 'voters':
                $query = $query."ORDER BY upvotes DESC, downvotes DESC, username";
                break;
            case 'popular':
                $query = $query."ORDER BY viewcount DESC, username";
                break;
            default:
                throw new Exception("getUsersWithSorting: Invalid sorting");
                break;
        }

        if($limit !== null && $offset !== null) {
            $query = $query." LIMIT ? OFFSET ?";
        }

        $stmt = $db->prepare($query);

        if($limit !== null && $offset !== null) {
            if($sort == 'new')
                $stmt->execute(array($now, $limit, $offset));
            else
                $stmt->execute(array($limit, $offset));
        } else {
            if($sort == 'new')
                $stmt->execute(array($now));
            else
                $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    function incProfileViews($userid) {
        global $db;
        try {
            $stmt = $db->prepare("UPDATE rogouser SET viewcount = (SELECT viewcount FROM rogouser WHERE userid = ?) + 1 WHERE userid = ?");
            $stmt->execute(array($userid, $userid));
        } catch (Exception $e) {
            $errors->addError('rogouser', 'error processing update of rogouser viewcount');
            $errors->addError('exception', $e->getMessage());
            throw ($errors);
        }
    }

    function getUserInfoByLogin($login, $pass_hash) {
        global $db;
        $response = array();

        $result = $db->prepare("SELECT permissiontype, userid, reputation FROM rogouser WHERE username = ? AND passhash = ?");
        $result->execute(array($login,$pass_hash));
        $user = $result->fetch();

        if($user) {
            $response['result'] = 'OK';
            $response['user'] = $user;
        } else {
            $response['result'] = 'NOK';
        }
        return $response;
    }

    function updateLastAccess($username) {
        global $db;

        $stmt = $db->prepare("UPDATE rogouser SET lastaccess = now() WHERE username = ?");
        $stmt->execute(array($username));
    }

    function updateUserReputation($userid, $score) {
        global $db;

        try {
            $stmt = $db->prepare("UPDATE rogouser SET reputation = (SELECT reputation FROM rogouser WHERE userid = ?) + ? WHERE userid = ?");
            $stmt->execute(array($userid, $score, $userid));
        } catch (Exception $e) {
            $errors->addError('rogouser', 'error processing update of rogouser reputation');
            $errors->addError('exception', $e->getMessage());
            throw ($errors);
        }
    }

    function getUserByUsername($username) {
        global $db;
        $result = $db->prepare("SELECT * FROM rogouser WHERE username = ?");
        $result->execute(array($username));
        return $result->fetch();
    }

    function getUserById($id) {
        global $db;
        $result = $db->prepare("SELECT * FROM rogouser WHERE userid = ?");
        $result->execute(array($id));
        return $result->fetch();
    }

    function getUserByEmail($email) {
        global $db;
        $result = $db->prepare("SELECT * FROM rogouser WHERE email = ?");
        $result->execute(array($email));
        return $result->fetch();
    }

    function validateUsername($username){
        if(preg_match('/^[A-Za-z0-9_.]{4,20}$/', $username)) {
            return true;
        }
        return false;
    }

    function validateEmail($email) {
        if(preg_match('/^[[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', $email)) {
            return true;
        }
        return false;
    }
?>