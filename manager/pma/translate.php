<?php

// wap phpmyadmin
// ionutvmi@gmail.com
// master-land.net

include "lib/settings.php";
include "lib/pagination.class.php";

connect_db($db);

// perp

if (isset($_GET['perp'])) {
    $_SESSION['perp'] = (int)$_POST['perp'];
}

$page = (int)$_GET['page'] == 0 ? "1" : (int)$_GET['page'];
$perP = (int)$_SESSION['perp'] == 0 ? "10" : (int)$_SESSION['perp'];
$total_pages = ceil(count((array)$lang) / $perP);

if ($page > $total_pages) $page = $total_pages;
$tmp_lang = (array)$lang;
$pma->title = $lang->translate;

include $pma->tpl . "header.tpl";

echo "$lang->Page: " . $page . "<div class='left'>" . $_var->home . "$lang->translate <hr size='1px'>";

if ($_POST['ok']) {
    $f_lang = file_get_contents("lang/index.php");
    foreach($_POST['i'] as $k => $v) {
        $v = str_ireplace('"', '\"', $v);
        $f_lang = preg_replace("/\\\$lang\[\"" . trim($k) . "\"\] = \"(.+?)\";/iU", "\$lang[\"" . $k . "\"] = \"" . $v . "\";", $f_lang);
    }

    file_put_contents("lang/index.php", $f_lang);
}

$_pag = new pagination;
$t_lang = $_pag->generate($tmp_lang, $perP);
echo "<form action='?page=" . ($page + 1) . "' method='post'>";

foreach($t_lang as $k => $v) {
    echo "$k <br/><input type='text' name='i[$k]' value='" . htmlentities($v, ENT_QUOTES) . "'><br/>";
    ++$i;
}

echo "<input name='ok' type='submit' value='" . $lang->translate . "/" . $lang->continue . "'></form> " . ($page > 1 ? "<a href='?page=" . ($page - 1) . "'>$lang->Back </a>" : "");

if (ceil(count($tmp_lang) / $perP) > 1) echo "<div class='pag'>$lang->Pages : " . $_pag->links() . "</div>";
?>

<hr>
<form action='?perp&<?php
foreach($_GET as $k => $v) {
    if ($k != 'perp') {
        echo urlencode($k) . "=" . urlencode($v) . "&";
    }
} ?>' method='post'>
<?php echo $lang->Show; ?> 

<select name='perp'>

<?php
foreach($_var->perp as $nr) {
    echo $nr == $_SESSION['perp'] ? "<option value='$nr' SELECTED>$nr</option>" : "<option value='$nr'>$nr</option>";
}
?>

</select>
<input type='submit' value='<?php
echo $lang->Per_Page; ?>'>
</form>
<hr>

<?php include $pma->tpl . 'footer.tpl'; ?>
