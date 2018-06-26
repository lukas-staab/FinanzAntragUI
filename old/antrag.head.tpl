<?php
if ($DEV){
    echo "<!-- antrag.head.tpl -->";
}

$classConfig = $form["_class"];
$classTitle = isset($classConfig["title"]) ? $classConfig["title"] : $form["type"];

$revConfig = $form["config"];
$revTitle = isset($revConfig["revisionTitle"]) ? $revConfig["revisionTitle"] : $form["revision"];

$targetRead = str_replace("//","/",$URIBASE."/").rawurlencode($antrag["token"])."";

if (isset($antrag))
    $h = "[{$antrag["id"]}] {$classTitle}";
else
    $h = "{$classTitle}";

?>

    <div class="container main col-md-10">
    <nav class="navbar navbar-default no-print">
        <div class="container-fluid">
            <div class="navbar-header">
                <a class="navbar-brand" href="<?php echo htmlspecialchars($targetRead); ?>"><?php echo htmlspecialchars($h); ?></a>
            </div>
            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <p class="navbar-text navbar-right"><?php echo htmlspecialchars($revTitle); ?></p>
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>

    <?php

    # vim:syntax=php