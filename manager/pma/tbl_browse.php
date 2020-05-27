<?php

// wap phpmyadmin
// ionutvmi@gmail.com
// master-land.net

include 'lib/settings.php';

connect_db($db);
$check = $db->query("SHOW DATABASES LIKE '" . $db->real_escape_string($_GET['db']) . "'");
$check = $check->num_rows;
$db_name = trim($_GET['db']);

// if no db exit

if ($db_name == '' OR $check == 0) {
    header("Location: main.php");
    exit;
}

// select db

$db->select_db($db_name);
$check = $db->query("SHOW TABLE STATUS LIKE '" . $db->real_escape_string($_GET['tb']) . "'");
$check = $check->num_rows;
$tb_name = trim($_GET['tb']);

// if no tb exit

if ($tb_name == '' OR $check == 0) {
    header("Location: main.php");
    exit;
}

// define url query

$_url = $_url_s = "db=" . urlencode($db_name) . "&tb=" . urlencode($tb_name);

// perp

if (isset($_GET['perp'])) {
    $_SESSION['perp'] = (int)$_POST['perp'];
}

// sort

if (isset($_GET['sort'])) {
    $_SESSION['sort'] = (int)$_GET['sort'];
}

$act = $_GET['act'];

if ($act == 'edit') {
    $_url.= "&col=" . urlencode($_GET['col']) . "&unq=" . $_GET['unq'];
    $cl = $db->query("SHOW FULL COLUMNS FROM " . PMA_bkq($tb_name));
    if (!$_POST['ok']) {
        $_q = "SELECT * FROM " . PMA_bkq($tb_name) . " WHERE " . base64_decode($_GET['unq']);
        if ($data = $db->query($_q)) {
            if ($data->num_rows < 1) {
                header("Location: ?$_url");
                exit;
            }

            $r_data = $data->fetch_assoc();
        }

        while ($c = $cl->fetch_assoc()) {
            $arr = PMA_extractFieldSpec($c['Type']);

            // strip the "BINARY" attribute, except if we find "BINARY(" because
            // this would be a BINARY or VARBINARY field type

            $arr['type'] = preg_replace('@BINARY([^(])@i', '', $arr['type']);
            $arr['type'] = preg_replace('@ZEROFILL@i', '', $arr['type']);
            $arr['type'] = preg_replace('@UNSIGNED@i', '', $arr['type']);

            // some types, for example longtext, are reported as
            // "longtext character set latin7" when their charset and / or collation
            // differs from the ones of the corresponding database.

            $tmp = strpos($arr['type'], 'character set');
            if ($tmp) {
                $arr['type'] = substr($arr['type'], 0, $tmp - 1);
            }

            $c['Default'] = $r_data[$c['Field']];
            $col[$c['Field']] = array_merge($arr, array(
                'Default' => $c['Default']
            ));
        }
    } else {
        $dat = $_POST['i'];
        $i = 0;
        while ($c = $cl->fetch_object()) {
            if (isSqlFunction($dat[$i])) $tq[] = PMA_bkq($c->Field) . " = " . $dat[$i];
            else $tq[] = PMA_bkq($c->Field) . " = '" . $db->real_escape_string($dat[$i]) . "'";
            ++$i;
        }

        $_q = "UPDATE " . PMA_bkq($tb_name) . " SET " . implode(',', $tq) . " WHERE " . base64_decode($_GET['unq']);
        if ($db->query($_q) !== TRUE) $_err = $db->error;
    }
} elseif ($act == 'drop') {
    $_url.= "&col=" . urlencode($_GET['col']) . "&unq=" . $_GET['unq'];
    if ($_POST['ok']) {
        $_q = "DELETE FROM " . PMA_bkq($tb_name) . " WHERE " . trim(base64_decode($_GET['unq'])) . " LIMIT 1";
        if ($db->query($_q) !== TRUE) $_err = $db->error;
    }
} elseif ($act == 'view') {
    $_url.= "&col=" . urlencode($_GET['col']) . "&unq=" . $_GET['unq'];
    $_q = "SELECT * FROM " . PMA_bkq($tb_name) . " WHERE " . base64_decode($_GET['unq']);
    if ($data = $db->query($_q)) {
        if ($data->num_rows < 1) {
            header("Location: ?$_url");
            exit;
        }

        $r_data = $data->fetch_object();
    }
} elseif ($act == 'multi') {
    $r_nm = $_POST['i'];
    if (!$r_nm OR !$_POST['do']) header("Location: ?" . $_url);
    $j = $_POST['j'];
    $v = 0;
    foreach($r_nm as $r) {
        if ($_POST['do'] != '2') {
            if ($_POST['ok']) {
                $_q = "DELETE FROM " . PMA_bkq($tb_name) . " WHERE " . trim(base64_decode($r)) . " LIMIT 1";
                if ($result = $db->query($_q)) {
                    $_msg[] = htmlentities($j[$v]) . ", ";
                }
                else {
                    $_err[] = $db->error;
                }
            }
            else {
                $_msg[0].= "<i>" . htmlentities($j[$v]) . "</i><br/> ";
                $_msg[1].= "<input type='hidden' name='i[]' value='" . $r . "'>n<input type='hidden' name='j[]' value='" . urlencode($j[$v]) . "'>n";
                ++$v;
            }
        }
        else {
            $_v[] = "(" . trim(base64_decode($r)) . ")";
        }
    }

    if ($_POST['do'] == '2') {
        $_SESSION['records'] = " WHERE " . implode(" OR ", $_v);
        header("Location: export.php?$_url_s");
        exit;
    }
} else {
    $_q = "SHOW FULL COLUMNS FROM " . PMA_bkq($tb_name);
    if ($data = $db->query($_q)) {
        while ($_d = $data->fetch_object()) {
            $_cols[] = $_d->Field;
        }
    }

    // let's select the column to display

    $column = ($_GET['col'] ? (in_array($_GET['col'], $_cols) ? $_GET['col'] : $_cols[0]) : $_cols[0]);
    $_url.= "&col=" . urlencode($column);

    // from search page

    if (isset($_GET['search2'])) {
        $search = "WHERE " . base64_decode($_SESSION['search']);
        $_url.= "&search2=1";
    }

    // search

    if (isset($_GET['search']) && trim($_GET['search']) != '') {
        $search = $db->real_escape_string(trim($_GET['search']));
        $_url.= "&search=$search";
        $search = "WHERE " . PMA_bkq($column) . " LIKE '%$search%'";
    }

    // total number of records and some pagination thingish

    $_q = "SELECT * FROM " . PMA_bkq($tb_name) . " $search";
    $data = $db->query($_q);
    $total_num_rows = $data->num_rows;
    $page = (int)$_GET['page'] == 0 ? 1 : (int)$_GET['page'];
    $perP = (int)$_SESSION['perp'] == 0 ? "10" : (int)$_SESSION['perp'];
    $sort = (int)$_SESSION['sort'] == 0 ? "ASC" : "DESC";
    $total_pages = ceil($total_num_rows / $perP);
    if ($page > $total_pages) $page = $total_pages;
    $start = (($page * $perP) - $perP);

    // just the ones we need on this page

    $_q = "SELECT * FROM " . PMA_bkq($tb_name) . " $search ORDER BY " . PMA_bkq($column) . " " . $sort . " LIMIT $start,$perP";
    if ($data = $db->query($_q)) {
        while ($_d = $data->fetch_array()) {
            $tb_data[] = $_d;

            // let's see what makes the record unique

            $_unq[] = getUniqueCondition($data, $_d);
        }
    }

    if ($data->num_rows > 0) {
    }
}

$pma->title = $lang->Browse;

include $pma->tpl . 'header.tpl';
include $pma->tpl . 'tbl_browse.tpl';
include $pma->tpl . 'footer.tpl';
