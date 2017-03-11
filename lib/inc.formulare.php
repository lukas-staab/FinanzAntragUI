<?php

loadForms();

function convertDBValueToUserValue($value, $type) {
  switch ($type) {
    case "money":
       $value = (string) $value;
      if ($value === false || $value == "") return $value;
      return number_format($value, 2, ',', ' ');
    default:
      return $value;
  }
}

function convertUserValueToDBValue($value, $type) {
  switch ($type) {
    case "titelnr":
      $value = trim(str_replace(" ", "", $value));
      $nv = "";
      for ($i = 0; $i < strlen($value); $i++) {
        if ($i % 4 == 1) $nv .= " ";
        $nv .= $value[$i];
      }
      return $nv;
    case "kostennr":
      $value = trim(str_replace(" ", "", $value));
      $nv = "";
      for ($i = 0; $i < strlen($value); $i++) {
        if ($i % 3 == 2) $nv .= " ";
        $nv .= $value[$i];
      }
      return $nv;
    case "kontennr":
      $value = trim(str_replace(" ", "", $value));
      $nv = "";
      for ($i = 0; $i < strlen($value); $i++) {
        if ($i % 2 == 0 && $i > 0) $nv .= " ";
        $nv .= $value[$i];
      }
      return $nv;
    case "money":
      return str_replace(" ", "", str_replace(",",".",str_replace(".", "", $value)));
    default:
      return $value;
  }
}

function registerForm( $type, $revision, $layout, $config ) {
  global $formulare;

  if (!isset($formulare[$type])) die("missing form-class $type");
  if (isset($formulare[$type][$revision])) die("duplicate form-id $type:$revision");
  $formulare[$type][$revision] = [
    "layout" => $layout,
    "config" => $config,
    "type" => $type,
    "revision" => $revision,
    "_class" => $formulare[$type]["_class"],
    "_perms" => mergePermission($formulare[$type]["_class"], $config, $type, $revision),
    "_categories" => mergePermission($formulare[$type]["_class"], $config, $type, $revision, "categories"),
    "_validate" => mergePermission($formulare[$type]["_class"], $config, $type, $revision, "validate"),
  ];
}

function mergePermission($classConfig, $revConfig, $type, $revision, $setting = "permission") {
  $perms = [];
  foreach ([$classConfig, $revConfig] as $config) {
    if (!isset($config[$setting])) continue;
    foreach ($config[$setting] as $id => $p) {
      if (isset($perms[$id])) die("$type:$revision: $setting $id has conflicting definitions");
      $perms[$id] = $p;
    }
  }
  return $perms;
}

function registerFormClass( $type, $config ) {
  global $formulare;

  if (isset($formulare[$type])) die("duplicate form-class $type");
  $formulare[$type] = [];
  $formulare[$type]["_class"] = $config;
}

function getFormClass( $type ) {
  global $formulare;

  if (!isset($formulare[$type])) die("unknown form-class $type");

  return $formulare[$type]["_class"];
}

function loadForms() {
  global $formulare;

  $handle = opendir(SYSBASE."/config/formulare");

  $files = [];
  while (false !== ($entry = readdir($handle))) {
    if (substr($entry, -4) !== ".php") continue;
    $files[] = $entry;
  }

  function cmp($ax, $bx)
  {
    $a = strlen($ax);
    $b = strlen($bx);

    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
  }

  $a = array(3, 2, 5, 6, 1);

  usort($files, "cmp");

  foreach ($files as $entry) {
    require SYSBASE."/config/formulare/".$entry;
  }

  closedir($handle);

}

function checkSinglePermission(&$i, &$c, &$antrag, &$form, $isCategory = false) {
  global $attributes;
  if ($i == "state") {
    $currentState = "draft";
    if (isset($form["_class"]["createState"]))
      $currentState = $form["_class"]["createState"];
    if ($antrag)
      $currentState = $antrag["state"];
    if ($currentState != $c)
      return false;
  } else if ($i == "creator") {
    if ($c == "self") {
      if ($antrag !== null && isset($antrag["creator"]) && ($antrag["creator"] != getUsername()))
        return false;
    } else {
      die("unkown creator test: $c");
    }
  } else if (substr($i,0,12) == "inOtherForm:") {
    $fieldDesc = substr($i, 12);
    $fieldValue = false;
    $fieldName = false;
    if ($fieldDesc == "referenceField") {
      if (!isset($form["config"]["referenceField"])) return false; #no such field
      $fieldName = $form["config"]["referenceField"]["name"];
    } elseif (substr($fieldDesc,0,6) == "field:") {
      $fieldName = substr($fieldDesc,6);
    } else {
      die ("inOtherForm: fieldDesc=$fildDesc not implemented");
    }
    if ($fieldValue === false && $fieldName !== false && $antrag !== null && isset($antrag["_inhalt"]))
      $fieldValue = getFormValueInt($fieldName, null, $antrag["_inhalt"], $fieldValue);
#    echo "\n<!-- checkSinglePermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i c=".json_encode($c).": fieldName = $fieldName fieldValue = ".(print_r($fieldValue,true))." -->\n";
    if ($fieldValue === false || $fieldValue == "")
      return false; # nothing given here
    $otherAntrag = getAntrag($fieldValue);
#    echo "\n<!-- checkSinglePermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i c=".json_encode($c).": otherAntrag = ".($otherAntrag === false ? "false" : "non-false")." -->\n";
    if ($otherAntrag === false) return false; # not readable. Ups.
    $otherForm =  getForm($otherAntrag["type"], $otherAntrag["revision"]);

    if (!is_array($c)) $c = [$c];
    foreach ($c as $permName) {
#      echo "\n<!-- checkSinglePermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i c=".json_encode($c).": evaluate $permName -->\n";
      if ($isCategory && !hasCategory($otherForm, $otherAntrag, $permName))
        return false;
      if (!$isCategory && !hasPermission($otherForm, $otherAntrag, $permName))
        return false;
    }
  } else if ($i == "hasPermission") {
    if (!is_array($c)) $c = [$c];
    foreach ($c as $permName) {
      if (!hasPermission($form, $antrag, $permName))
        return false;
    }
  } else if ($i == "hasPermissionNoAdmin") {
    if (!is_array($c)) $c = [$c];
    foreach ($c as $permName) {
      if (!hasPermission($form, $antrag, $permName, false))
        return false;
    }
  } else if ($i == "hasCategory") {
    if (!is_array($c)) $c = [$c];
    foreach ($c as $permName) {
      if (!hasCategory($form, $antrag, $permName))
        return false;
    }
  } else if ($i == "notHasCategory") {
    if (!is_array($c)) $c = [$c];
    foreach ($c as $permName) {
      if (!hasCategory($form, $antrag, $permName))
        return true;
    }
    return false;
  } else if ($i == "group") {
    if (!is_array($c)) $c = [$c];
    foreach ($c as $groupName) {
      if (!hasGroup($groupName))
        return false;
    }
  } else if (substr($i, 0, 6) == "field:") {
    $fieldName = substr($i, 6);
    if ($antrag !== null && isset($antrag["_inhalt"])) {
      $value = getFormValueInt($fieldName, null, $antrag["_inhalt"], null);
      if (substr($c,0,5) == "isIn:") {
        $in = substr($c,5);
        $permittedValues = [];
        if ($value === null) return false;
        if ($in == "data-source:own-orgs") {
          $permittedValues = $attributes["gremien"];
        } else if ($in == "data-source:own-mail") {
          $permittedValues = array_values($attributes["mail"]);
          if (isset($attributes["extra-mail"]))
            $permittedValues = array_merge($permittedValues, array_values($attributes["extra-mail"]));
        } else {
          die("isIn test $in (from $c) not implemented");
        }
        if (!in_array($value, $permittedValues))
          return false;
      } elseif (substr($c,0,2) == "<=") {
        $cmpVal = substr($c,2);
        if ($value > $cmpVal)
          return false;
      } elseif (substr($c,0,2) == ">=") {
        $cmpVal = substr($c,2);
        if ($value < $cmpVal)
          return false;
      } elseif (substr($c,0,2) == "==") {
        $cmpVal = substr($c,2);
        if ($value != $cmpVal)
          return false;
      } elseif (substr($c,0,1) == "<") {
        $cmpVal = substr($c,1);
echo "\n<!-- $fieldName = $value < $cmpVal -->\n";
        if ($value >= $cmpVal)
          return false;
      } elseif (substr($c,0,1) == ">") {
        $cmpVal = substr($c,1);
        if ($value <= $cmpVal)
          return false;
      } else {
        die("field test $c not implemented");
      }
    }
    /* antrag === null -> muss erst noch passend ausgefüllt werden (e.g. bei canCreate) */
    /* antrag !== null aber !isset(_inhalt) -> muss erst noch passend ausgefüllt werden (e.g. can alter state before create) */
  } else {
    die("permission type $i not implemented");
  }
  return true;
}

function checkPermissionLine(&$p, &$antrag, &$form, $isCategory) {
  foreach ($p as $i => $c) {
    $tmp = checkSinglePermission($i, $c, $antrag, $form, $isCategory);
#    echo "\n<!-- checkSinglePermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i c=".json_encode($c)." => ".($tmp ? "true":"false")." -->\n";
    if (!$tmp)
      return false;
  }
  return true;
}

function hasPermission(&$form, $antrag, $permName, $adminOk = true) {
  static $stack = false;

  if (!isset($form["_perms"][$permName]))
    return false;

  $pp = $form["_perms"][$permName];
  if ($antrag === null || !isset($antrag["id"]))
    $aId = "null";
  else
    $aId = $antrag["id"];

  $permId = $form["type"].":".$form["revision"].":".$aId.".".$permName;
  if ($stack === false)
    $stack = [];
  if (in_array($permId, $stack))
    return false;
  array_push($stack, $permId);

#  echo "\n<!-- hasPermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $permName => to be evaluated -->\n";

  $ret = hasPermissionImpl($form, $antrag, $pp, $permName, $adminOk);

  array_pop($stack);

#  echo "\n<!-- hasPermission: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $permName => ".($ret ? "true":"false")." -->\n";

  return $ret;
}

function hasCategory(&$form, $antrag, $permName) {
  static $stack = false;

  if (!isset($form["_categories"][$permName]))
    return false;

  $pp = $form["_categories"][$permName];
  if ($antrag === null || !isset($antrag["id"]))
    $aId = "null";
  else
    $aId = $antrag["id"];

  $permId = $form["type"].":".$form["revision"].":".$aId.".".$permName;
  if ($stack === false)
    $stack = [];
  if (in_array($permId, $stack))
    return false;
  array_push($stack, $permId);

#  echo "\n<!-- hasCategory: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $permName => to be evaluated -->\n";

  $ret = hasPermissionImpl($form, $antrag, $pp, $permName, false, true);

  array_pop($stack);

#  echo "\n<!-- hasCategory: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $permName => ".($ret ? "true":"false")." -->\n";

  return $ret;
}

function hasPermissionImpl(&$form, &$antrag, &$pp, $permName = "anonymous", $adminOk = true, $isCategory = false) {
  global $ADMINGROUP;

  if ($adminOk && hasGroup($ADMINGROUP))
    return true;

  $ret = false;

  if (is_bool($pp))
    $ret = $pp;

  if (is_array($pp)) {
    foreach($pp as $i => $p) {
      $tmp = checkPermissionLine($p, $antrag, $form, $isCategory);
#      echo "\n<!-- checkPermissionLine: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i p=".json_encode($p)." => ".($tmp ? "true":"false")." -->\n";
      if (!$tmp)
        continue;
      $ret = true;
      break;
    }
  }

#  echo "\n<!-- hasPermissionImpl: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $permName => ".($ret ? "true":"false")." -->\n";

  return $ret;
}

function isValid($antragId, $validateName, &$msgs = []) {
  static $stack = false;

  $ctrl = [ "render" => [ "no-form" ] ];
  $tmp = renderOtherAntrag($antragId, $ctrl);
  if ($tmp === false) return false;
  $form = $tmp["form"];
  $ctrl = $tmp["ctrl"];
  $antrag = $tmp["antrag"];

  if (!isset($form["_validate"][$validateName]))
    return true; # nothing to violate anyway
  $pp = $form["_validate"][$validateName];

  $validateId = $antragId.".".$validateName;
  if ($stack === false)
    $stack = [];
  if (in_array($validateId, $stack))
    return false;
  array_push($stack, $validateId);

#  echo "\n<!-- isValid: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $validateName => to be evaluated -->\n";

  $ret = isValidImpl($form, $antrag, $ctrl, $pp, $validateName, $msgs);

  array_pop($stack);

#  echo "\n<!-- isValid: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $validateName => ".($ret ? "true":"false")." -->\n";

  return $ret;
}

function isValidImpl(&$form, &$antrag, &$ctrl, &$pp, &$validateName, &$msgs) {

  $ret = true;

  if (is_bool($pp))
    $ret = $pp;

  if (is_array($pp)) {
    foreach($pp as $i => $p) {
      $tmp = checkValidLine($p, $antrag, $ctrl, $form, $msgs);
#      echo "\n<!-- checkValidLine: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." i=$i p=".json_encode($p)." => ".($tmp ? "true":"false")." -->\n";
      if ($tmp)
        continue;
      $ret = false;
      break;
    }
  }

#  echo "\n<!-- hasValidImpl: {$form["type"]} {$form["revision"]} ".($antrag === null ? "w/o antrag":"w antrag")." $validateName => ".($ret ? "true":"false")." -->\n";

  return $ret;
}

# once per form
function checkValidLine(&$p, &$antrag, &$ctrl, &$form, &$msgs) {
  if (isset($p["state"]) && ($antrag["state"] != $p["state"])) return true; # not selected
  if (isset($p["revision"]) && ($antrag["revision"] != $p["revision"])) return true; # not selected
  if (isset($p["doValidate"]) && !isValid($antrag["id"], $p["doValidate"], $msgs)) return false; # invalid content
  if (isset($p["id"])) { # a field selector
    $found = 0;
    foreach ($antrag["_inhalt"] as $inhalt0) {
      $ret = checkValidLineField($p, $antrag, $ctrl, $form, $inhalt0, $msgs);
      if ($ret === false) {
        $msgs[] = "{$inhalt0["fieldname"]} validation failed";
        return false;
      }
      if ($ret === null)
        $found++;
    }
    if ($found == 0) {
      $inhalt0 = [ "fieldname" => $p["id"], "contenttype" => null, "value" => null ];
      $ret = checkValidLineField($p, $antrag, $ctrl, $form, $inhalt0, $msgs);
      if ($ret === false) {
        $msgs[] = "{$inhalt0["fieldname"]} validation failed";
        return false;
      }
    }
  }
  if (isset($p["sum"])) { # a sum expression
    $src = [];
    $value = evalPrintSum($p["sum"], $ctrl["_render"]->addToSumValue, $src);
    $value = (float) number_format($value, 2, ".", "");

    if (isset($p["maxValue"]) && ($value > $p["maxValue"])) {
      $msgs[] = "sum {$p["sum"]} too big";
      return false;
    }
    if (isset($p["minValue"]) && ($value < $p["minValue"])) {
      $msgs[] = "sum {$p["sum"]} too small";
      return false;
    }
  }
  return true;
}

# once per field
function checkValidLineField(&$p, &$antrag, &$ctrl, &$form, &$inhalt, &$msgs) {
  if (isset($p["id"]) && ($p["id"] != $inhalt["fieldname"]) && (substr($inhalt["fieldname"], 0, strlen($p["id"]) + 1) != $p["id"]."[") )
    return null; # not selected
  if (($inhalt["contenttype"] === "otherForm") && isset($p["otherForm"])) {
    # check if a valid other form is selected
    if ($inhalt["value"] == "") return false;
    $otherAntrag = getAntrag($inhalt["value"]);
    $found = false;
    foreach ($p["otherForm"] as $of) {
      if (isset($of["type"]) && $of["type"] != $otherAntrag["type"]) continue;
      if (isset($of["revision"]) && $of["revision"] != $otherAntrag["revision"]) continue;
      if (isset($of["state"]) && $of["state"] != $otherAntrag["state"]) continue;
      if (isset($of["revisionIsYearFromField"]) && !isset($antrag["_inhalt"])) continue;
      if (isset($of["revisionIsYearFromField"])) {
        $fieldValue = getFormValueInt($of["revisionIsYearFromField"], null, $antrag["_inhalt"], "");
        if (empty($fieldValue)) continue;
        $year = substr($fieldValue,0,4);
        if ($otherAntrag["revision"] != $year) continue;
      }
      if (isset($of["fieldMatch"])) {
        $fieldMatchOk = true;
        foreach ($of["fieldMatch"] as $c) {
          $otherValue = getFormValueInt($c["otherFormFieldName"], null, $otherAntrag["_inhalt"], "");
          $thisValue = getFormValueInt($c["thisFormFieldName"], null, $antrag["_inhalt"], "");
          switch ($c["condition"]) {
            case "matchYear":
              $thisYear = substr($thisValue,0,4);
              $otherYear = substr($otherValue,0,4);
              if ($otherYear != $thisYear)
                $fieldMatchOk = false;
              break;
            case "equal":
              if ($otherValue != $thisValue)
                $fieldMatchOk = false;
              break;
            default:
              die("condition not implemented: ".$c["condition"]);
              break;
          }
        }
        if (!$fieldMatchOk)
          continue;
      }
      if (isset($of["validate"]) && !isValid($otherAntrag["id"], $of["validate"])) continue;
      $found = true;
      break;
    }
    if (!$found) return false;
  }
  if (isset($p["value"]) && (($pos = strpos($p["value"],":")) !== false)) {
    $prefix = substr($p["value"],0,$pos);
    $remainder = substr($p["value"],$pos+1);
    switch ($prefix) {
      case "is":
        switch ($remainder) {
          case "notEmpty":
            if ($inhalt["value"] == "") return false;
            break;
          case "empty":
            if ($inhalt["value"] != "") return false;
            break;
          default:
            die("not implemented validation: value \"$prefix\" \"$remainer\"");
        }
      break;
      case "equals":
        if (((string) $inhalt["value"]) != $remainder) return false;
      break;
      default:
        die("not implemented validation: value \"$prefix\" \"$remainer\"");
    }
  }
  return true;
}
 
function getForm($type, $revision) {
  global $formulare;

  if (!isset($formulare[$type])) return false;
  if (!isset($formulare[$type][$revision])) return false;

  return $formulare[$type][$revision];
}

function getFormLayout($type, $revision) {
  global $formulare;

  if (!isset($formulare[$type])) return false;
  if (!isset($formulare[$type][$revision])) return false;

  return $formulare[$type][$revision]["layout"];
}

function getFormConfig($type, $revision) {
  global $formulare;

  if (!isset($formulare[$type])) return false;
  if (!isset($formulare[$type][$revision])) return false;

  return $formulare[$type][$revision]["config"];
}

function getBaseName($name) {
  $matches = [];
  if (preg_match("/^([^\[\]]*)(.*)/", $name, $matches)) {
    return $matches[1];
  }
  return false;
}

function getFormName($name) {
  $matches = [];
  if (preg_match("/^formdata\[([^\]]*)\](.*)/", $name, $matches)) {
    return $matches[1].$matches[2];
  }
  return false;
}

function getFormNames($name) {
  $matches = [];
  if (preg_match("/^formdata\[([^\]]*)\](.*)/", $name, $matches)) {
    return [ $matches[1], $matches[2] ];
  }
  return false;
}

function getFormValue($name, $type, $values, $defaultValue = false) {
  $name = getFormName($name);
  if ($name === false)
    return $defaultValue;
  return getFormValueInt($name, $type, $values, $defaultValue);
}

function getFormValueInt($name, $type, $values, $defaultValue = false) {
  foreach($values as $row) {
    if ($row["fieldname"] != $name)
      continue;
    if ($type !== null && $row["contenttype"] !== null && $row["contenttype"] != $type) {
      add_message("Feld $name: erwarteter Typ = \"$type\", erhaltener Typ = \"{$row["contenttype"]}\"");
      continue;
    }
    return $row["value"];
  }
  return $defaultValue;
}

function getFormEntry($name, $type, $values) {
  foreach($values as $row) {
    if ($row["fieldname"] != $name)
      continue;
    if ($type !== null && $row["contenttype"] !== null && $row["contenttype"] != $type) {
      add_message("Feld $name: erwarteter Typ = \"$type\", erhaltener Typ = \"{$row["contenttype"]}\"");
      continue;
    }
    return $row;
  }
  return false;
}

function getFormEntries($name, $type, $values, $value = null) {
  $ret = [];
  foreach($values as $row) {
    if ($row["fieldname"] != $name && (substr($row["fieldname"], 0, strlen($name."[")) != $name."["))
      continue;
    if ($type !== null && $row["contenttype"] != $type) {
      add_message("Feld $name: erwarteter Typ = \"$type\", erhaltener Typ = \"{$row["contenttype"]}\"");
      continue;
    }
    if ($value !== null && $row["value"] != $value)
      continue;
    $ret[] = $row;
  }
  return $ret;
}

function getFormFile($name, $values) {
  $name = getFormName($name);
  if ($name === false)
    return false;

  foreach($values as $row) {
    if ($row["fieldname"] != $name)
      continue;
    return $row;
  }
  return false;
}

function getFormFiles($name, $values) {
  $name = getFormName($name);
  if ($name === false)
    return false;

  $ret = [];
  foreach($values as $row) {
    if ($row["fieldname"] != $name && (substr($row["fieldname"], 0, strlen($name."[")) != $name."["))
      continue;
    $ret[] = $row;
  }
  return $ret;
}

function newTemplatePattern($ctrl, $value) {
  $tPattern = "<placeholder:".uniqid()."/>";
  $ctrl["_render"]->templates[$tPattern] = $value;
  return $tPattern;
}

function renderForm($form, $ctrl = false) {

  if (!isset($form["layout"]))
    die("renderForm: \$form has no layout");

  return renderFormImpl($form, $ctrl);
}

function renderFormImpl(&$form, &$ctrl) {
  global $renderFormTrace;

  static $stack = false;

  if ($stack === false) $stack = [];
  if (isset($ctrl["_values"])) {
    if (in_array($ctrl["_values"]["id"], $stack)) {
      echo "form {$ctrl["_values"]["id"]} already on stack<br>\n";
      return false;
    }
    array_push($stack, $ctrl["_values"]["id"]);
  }
#  $renderFormTrace[] = [ "formStack" => $stack, "funcStack" => xdebug_get_function_stack()]; #$ctrl["_values"]["id"];
#  $renderFormTrace[] = $ctrl["_values"]["id"];

  $layout = $form["layout"];

  if (!is_array($ctrl))
    $ctrl = [];

  if (isset($form["_class"]))
    $ctrl["_class"] = $form["_class"];
  if (isset($form["config"]))
    $ctrl["_config"] = $form["config"];

  $ctrl["_render"] = new stdClass();
  $ctrl["_render"]->displayValue = false;
  $ctrl["_render"]->templates = [];
  $ctrl["_render"]->parentMap = []; /* map currentName => parentName */
  $ctrl["_render"]->currentParent = false;
  $ctrl["_render"]->currentParentRow = false;
  $ctrl["_render"]->currentRowId = false;
  $ctrl["_render"]->postHooks = []; /* e.g. ref-field */
  $ctrl["_render"]->addToSumMeta = [];
  $ctrl["_render"]->addToSumValue = [];
  $ctrl["_render"]->addToSumValueByRowRecursive = [];
  $ctrl["_render"]->referencedBy = []; /* tableRowReferenced -> tableRowWhereReferenceIs */
  $ctrl["_render"]->referencedByOtherForm = []; /* otherFormId -> myFieldName -> tableRowWhereReferenceIs */
  $ctrl["_render"]->otherForm = [];
  $ctrl["_render"]->numTableRows = [];
  $ctrl["_render"]->rowIdToNumber = [];
  $ctrl["_render"]->rowNumberToId = [];

  if (!isset($ctrl["render"]))
    $ctrl["render"] = [];

  ob_start();
  foreach ($layout as $item) {
    renderFormItem($item, $ctrl);
  }
  $txt = ob_get_contents();
  ob_end_clean();

  foreach($ctrl["_render"]->postHooks as $hook) {
    $hook($ctrl);
  }

  $txt = processTemplates($txt, $ctrl);

  echo $txt;

  if (isset($ctrl["_values"])) {
    array_pop($stack);
  }

  return true;
}


function processTemplates($txt, $ctrl) {
  if (!isset($ctrl["_render"]))
    return $txt;
  return str_replace(array_keys($ctrl["_render"]->templates), array_values($ctrl["_render"]->templates), $txt);
}

function isNoForm($layout, $ctrl) {
  $noForm = in_array("no-form", $ctrl["render"]);
  $noFormCb = in_array("no-form-cb", $ctrl["render"]);
  $noFormMarkup = in_array("no-form-markup", $ctrl["render"]);
  $noFormCompress = in_array("no-form-compress", $ctrl["render"]);
  if ($noFormCb) {
    $noForm |= $ctrl["no-form-cb"]($layout, $ctrl);
  }
  return Array ($noForm, $noFormMarkup, $noFormCompress);
}

function renderFormItem($layout,$ctrl = false) {

  if (!isset($layout["id"])) {
    echo "Missing \"id\" in ";
    print_r($layout);
    die();
  }

  if (!isset($layout["opts"]))
   $layout["opts"] = [];

  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  if (!isset($ctrl["wrapper"])) {
    $wrapper = "div";
  } else {
    $wrapper = $ctrl["wrapper"];
    unset($ctrl["wrapper"]);
  }

  if (isset($ctrl["class"]))
    $classes = $ctrl["class"];
  else
    $classes = [];

  if (isset($layout["editWidth"]) && !$noForm)
    $layout["width"] = $layout["editWidth"];
  if (isset($layout["width"]))
    $classes[] = "col-xs-{$layout["width"]}";

  $ctrl["id"] = $layout["id"];
  $ctrl["name"] = "formdata[{$layout["id"]}]";
  $ctrl["orig-name"] = $ctrl["name"];
  $ctrl["orig-id"] = $ctrl["id"];

  if (!isset($ctrl["suffix"]))
   $ctrl["suffix"] = [];
  foreach($ctrl["suffix"] as $suffix) {
    $ctrl["name"] .= "[{$suffix}]";
    $ctrl["orig-name"] .= "[]";
    if ($suffix !== false) {
      $ctrl["id"] .= "-".$suffix;
    }
  }
  $ctrl["id"] = str_replace(".", "-", $ctrl["id"]);
  $ctrl["orig-id"] = str_replace(".", "-", $ctrl["orig-id"]);

  $cls = [];
  if ((!$noFormMarkup && !$noFormCompress) || !$noForm)
    $cls[] = "form-group";
  if (in_array("hasFeedback", $layout["opts"]))
    $cls[] = "has-feedback";

  if ($noForm)
    $cls[] = "no-form-grp";
  else
    $cls[] = "form-grp";
  if ($noForm && in_array("hideableDuringRead", $layout["opts"]))
    $cls[] = "hideable-during-read";

  $ctrl["readonly"] = false;
  if (isset($layout["toggleReadOnly"])) {
    /* check readonly state of element, needs to be checkbox or radio */
    list ($elId, $elVal) = $layout["toggleReadOnly"];
    $value = "";
    if (isset($ctrl["_values"])) {
      $value = getFormValueInt($elId, null, $ctrl["_values"]["_inhalt"], $value);
    }
    $isReadOnly = ($elVal != $value);
    $ctrl["readonly"] = $isReadOnly;
  } elseif (in_array("readonly", $layout["opts"]))
    $ctrl["readonly"] = true;

  ob_start();
  switch ($layout["type"]) {
    case "h1":
    case "h2":
    case "h3":
    case "h4":
    case "h5":
    case "h6":
    case "plaintext":
      $isNotEmpty = renderFormItemPlainText($layout,$ctrl);
      break;
    case "group":
      $isNotEmpty = renderFormItemGroup($layout,$ctrl);
      break;
    case "signbox":
      $isNotEmpty = renderFormItemSignBox($layout,$ctrl);
      break;
    case "text":
    case "number":
    case "titelnr":
    case "kostennr":
    case "kontennr":
    case "email":
    case "url":
    case "iban":
      $isNotEmpty = renderFormItemText($layout,$ctrl);
      break;
    case "checkbox":
      $isNotEmpty = renderFormItemCheckbox($layout,$ctrl);
      break;
    case "radio":
      $isNotEmpty = renderFormItemRadio($layout,$ctrl);
      break;
    case "otherForm":
      $isNotEmpty = renderFormItemOtherForm($layout,$ctrl);
      break;
    case "money":
      $isNotEmpty = renderFormItemMoney($layout,$ctrl);
      break;
    case "textarea":
      $isNotEmpty = renderFormItemTextarea($layout,$ctrl);
      break;
    case "select":
    case "ref":
      $isNotEmpty = renderFormItemSelect($layout,$ctrl);
      break;
    case "date":
      $isNotEmpty = renderFormItemDate($layout,$ctrl);
      break;
    case "daterange":
      $isNotEmpty = renderFormItemDateRange($layout,$ctrl);
      break;
    case "table":
      $isNotEmpty = renderFormItemTable($layout,$ctrl);
      break;
    case "file":
      $isNotEmpty = renderFormItemFile($layout,$ctrl);
      break;
    case "multifile":
      $isNotEmpty = renderFormItemMultiFile($layout,$ctrl);
      break;
    case "invref":
      $isNotEmpty = renderFormItemInvRef($layout,$ctrl);
      break;
    default:
      ob_end_flush();
      echo "<pre>"; print_r($layout); echo "</pre>";
      die("Unkown form element meta type: ".$layout["type"]);
  }
  $txt = ob_get_contents();
  ob_end_clean();

  if (!$noForm && in_array("hide-edit", $layout["opts"]))
    $isNotEmpty = false;

  if (!$noFormMarkup) {
    echo "<$wrapper class=\"".implode(" ", $classes)."\" data-formItemType=\"".htmlspecialchars($layout["type"])."\"";
    echo " style=\"";
    if (isset($layout["max-width"]))
      echo "max-width: {$layout["max-width"]};";
    if (isset($layout["min-width"]))
      echo "min-width: {$layout["min-width"]};";
    echo "\"";
    echo ">";
  }

  if ($isNotEmpty !== false) {
    if (!$noFormMarkup)
      echo "<div class=\"".join(" ", $cls)."\">";
    if (!$noForm)
      echo "<input type=\"hidden\" value=\"{$layout["type"]}\" name=\"formtype[".htmlspecialchars($layout["id"])."]\"/>";

    if (isset($layout["title"]) && isset($layout["id"]))
      echo "<label class=\"control-label\" for=\"{$ctrl["id"]}\">".htmlspecialchars($layout["title"])."</label>";
    elseif (isset($layout["title"]))
      echo "<label class=\"control-label\">".htmlspecialchars($layout["title"])."</label>";

    echo $txt;

    if (!$noForm)
      echo '<div class="help-block with-errors"></div>';
    if (!$noFormMarkup)
      echo "</div>";
  }

  if (!$noFormMarkup) {
    if (isset($layout["width"]))
      echo "</$wrapper>";
    else
      echo "</$wrapper>";
  }

  return $isNotEmpty;

}

function renderFormItemPlainText($layout, $ctrl) {
  $value = "";
  if (isset($layout["value"]))
    $value = $layout["value"];
  if (isset($layout["autoValue"])) {
    if (substr($layout["autoValue"],0,6) == "class:") {
      $field = substr($layout["autoValue"], 6);
      if (isset($ctrl["_class"]) && $ctrl["_class"][$field])
        $value = $ctrl["_class"][$field];
    }
  }
  $value = htmlspecialchars($value);
  $value = implode("<br/>", explode("\n", $value));
  switch ($layout["type"]) {
    case "h1":
    case "h2":
    case "h3":
    case "h4":
    case "h5":
    case "h6":
      $elem = $layout["type"];
      break;
    default:
      $elem = "div";
  }
  $tPattern = newTemplatePattern($ctrl, $value);
  echo "<${elem}>{$tPattern}</${elem}>";
}

function renderFormItemGroup($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  if (in_array("well", $layout["opts"]))
     echo "<div class=\"well\">";

  $rowTxt = [];
  $isEmpty = true;

  foreach ($layout["children"] as $child) {
    $ctrl["_render"]->displayValue = true;
    ob_start();
    $isNotEmpty = renderFormItem($child, $ctrl);
    $childTxt = ob_get_contents();
    $isEmpty = $isEmpty && ($isNotEmpty === false);
    ob_end_clean();
    if (isset($child["editWidth"]) && !$noForm)
      $child["width"] = $child["editWidth"];
    if (isset($child["width"]) && $child["width"] == -1) {
      // hide
    } else  {
      echo $childTxt;
    }
    if (isset($child["opts"]) && in_array("title", $child["opts"])) {
      $rowTxt[] = $ctrl["_render"]->displayValue;
    }
  }
  if (in_array("well", $layout["opts"]))
    echo "<div class=\"clearfix\"></div></div>";

  $ctrl["_render"]->displayValue = implode(", ", $rowTxt);

  if ($isEmpty) return false;
}

function renderFormItemOtherForm($layout,$ctrl) {
  global $URIBASE, $nonce;

  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);
  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } elseif (isset($layout["value"])) {
    $value = $layout["value"];
  }

  if (!$noForm && $ctrl["readonly"]) {
    $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }
  if ($value != "")
    $ctrl["_render"]->referencedByOtherForm[(int) $value][$layout["id"]][] = $ctrl["_render"]->currentParent."[".$ctrl["_render"]->currentParentRow."]";

  if ($noForm) {
    echo '<div>';
    echo '<span class="glyphicon glyphicon glyphicon-link align-top" aria-hidden="true"></span>';

    $otherAntrag = false;
    if ($value === "") {
      echo '<i>Keine Angabe</i>';
    } else {
      $otherAntrag = dbGet("antrag", ["id" => $value]);
      if ($otherAntrag === false) {
        echo "<i>ungültiger Wert: ".newTemplatePattern($ctrl, htmlspecialchars($value))."</i>";
      }
    }

    $readPermitted = false;
    if ($otherAntrag !== false) {
      $otherInhalt = dbFetchAll("inhalt", ["antrag_id" => $otherAntrag["id"]]);
      $otherAntrag["_inhalt"] = $otherInhalt;

      $otherForm = getForm($otherAntrag["type"], $otherAntrag["revision"]);
      $readPermitted = hasPermission($otherForm, $otherAntrag, "canRead");

      if (!$readPermitted) {
        echo "<i>Formular nicht lesbar: ".newTemplatePattern($ctrl, htmlspecialchars($value))."</i>";
      }
    }

    if ($readPermitted) {
      $classTitle = "[{$otherAntrag["type"]}]";
      $classConfig = $otherForm["_class"];
      if (isset($classConfig["title"]))
        $classTitle = $classConfig["title"];
      if (isset($classConfig["shortTitle"]))
        $classTitle = $classConfig["shortTitle"];
      $text = getAntragDisplayTitle($otherAntrag, $otherForm["config"]);
      $target = str_replace("//","/",$URIBASE."/").rawurlencode($otherAntrag["token"]);

      echo "<a href=\"".htmlspecialchars($target)."\" target=\"_blank\">";
      echo newTemplatePattern($ctrl, "[{$otherAntrag["id"]}] {$classTitle}: ".str_replace("\n","<br/>",implode(" ",$text)));
      echo "</a>";
    }

    echo '</div>';
    return;
  }

  $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
  echo "<div class=\"input-group\">";
  echo "<span class=\"input-group-addon extra-text\"></span>";
  echo "<input class=\"form-control\" type=\"{$layout["type"]}\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
  if (in_array("required", $layout["opts"]))
    echo " required=\"required\"";

  echo " data-remote=\"".htmlspecialchars(str_replace("//","/",$URIBASE."/")."validate.php?ajax=1&action=validate.otherForm&nonce=".urlencode($nonce))."\"";
  echo " data-remote-error=\"Ungültige Formularnummer\"";
  echo " data-extra-text=\"".htmlspecialchars(str_replace("//","/",$URIBASE."/")."validate.php?ajax=1&action=text.otherForm&nonce=".urlencode($nonce))."\"";
  echo " value=\"{$tPattern}\"";
  echo '>';
  echo "</div>";
  if (in_array("hasFeedback", $layout["opts"]))
    echo '<span class="glyphicon form-control-feedback" aria-hidden="true"></span>';
#  echo '<div class="extra-text pull-right"></div>';
}

function renderFormItemRadio($layout,$ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } elseif (isset($layout["value"])) {
    $value = $layout["value"];
  } elseif (!$noForm && isset($layout["prefill"]) && $layout["prefill"] == "user:mail") {
    $value = getUserMail();
  } elseif (!$noForm && isset($layout["prefill"]) && substr($layout["prefill"],0,6) == "value:") {
    $value = substr($layout["prefill"],6);
  }

  if (!$noForm && $ctrl["readonly"]) {
    if ($value == $layout["value"]) {
      $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
      echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
      echo " value=\"{$tPattern}\"";
      echo '>';
    }
    $noForm = true;
  }

  if ($noForm) {
    echo '<div class="radio">';
    if ($value == $layout["value"]) {
      #echo '<span class="glyphicon glyphicon-ok-circle align-top" aria-hidden="true"></span>';
      echo '<span class="glyphicon glyphicon-check align-top" aria-hidden="true"></span>';
    } else {
      echo '<span class="glyphicon glyphicon-unchecked align-top" aria-hidden="true"></span>';
    }
    echo '<label>';
    echo str_replace("\n","<br/>",htmlspecialchars($layout["text"]));
    echo '</label>';
    echo '</div>';
    return;
  }

  echo '<div class="radio">';
  echo '<label><input type="radio" name="'.htmlspecialchars($ctrl["name"]).'" value="'.htmlspecialchars($layout["value"]).'"';
  if ($value == $layout["value"]) {
    echo " checked=\"checked\"";
  }
  if (in_array("required", $layout["opts"]))
    echo " required=\"required\"";
  if (in_array("toggleReadOnly", $layout["opts"]))
    echo " data-isToggleReadOnly=\"true\"";
  echo '>'.str_replace("\n","<br/>",htmlspecialchars($layout["text"])).'</label>';
  echo '</div>';
}

function renderFormItemCheckbox($layout,$ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  }

  if (!$noForm && $ctrl["readonly"]) {
    if ($value == $layout["value"]) {
      $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
      echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
      echo " value=\"{$tPattern}\"";
      echo '>';
    }
    $noForm = true;
  }

  if ($noForm) {
    echo '<div class="checkbox">';
    if ($value == $layout["value"]) {
      #echo '<span class="glyphicon glyphicon-ok-circle align-top" aria-hidden="true"></span>';
      echo '<span class="glyphicon glyphicon-check align-top" aria-hidden="true"></span>';
    } else {
      echo '<span class="glyphicon glyphicon-unchecked align-top" aria-hidden="true"></span>';
    }
    echo '<label>';
    echo str_replace("\n","<br/>",htmlspecialchars($layout["text"]));
    echo '</label>';
    echo '</div>';
    return;
  }

  echo '<div class="checkbox">';
  echo '<label><input type="checkbox" name="'.htmlspecialchars($ctrl["name"]).'" value="'.htmlspecialchars($layout["value"]).'"';
  if ($value == $layout["value"]) {
    echo " checked=\"checked\"";
  }
  if (in_array("required", $layout["opts"]))
    echo " required=\"required\"";
  if (in_array("toggleReadOnly", $layout["opts"]))
    echo " data-isToggleReadOnly=\"true\"";
  echo '>'.str_replace("\n","<br/>",htmlspecialchars($layout["text"])).'</label>';
  echo '</div>';
}

function printSumId($psIds) {
  if (!is_array($psIds)) $psIds = [ $psIds ];
  $r = [];
  foreach ($psIds as $psId) {
    if (substr($psId,0,5) == "expr:")
      $r[] = md5($psId);
    else
      $r[] = $psId;
  }
  return implode(" ", $r);
}

function renderFormItemSignBox($layout, $ctrl) {
  global $nonce, $URIBASE, $attributes, $GremiumPrefix;

  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  }

  $ctrl["_render"]->displayValue = htmlspecialchars($value);
  $tPattern = newTemplatePattern($ctrl, htmlspecialchars($value));

  if (!$noForm && $ctrl["readonly"]) {
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }

  if ($noForm) {
    if (!$noFormMarkup) {
      echo "<div class=\"form-control signbox\">";
    }
    echo $tPattern;
    if (!$noFormMarkup) {
      echo "</div>";
    }
  } else {
    $isChecked = ($value != "");
    $newValue = ($isChecked ? $value : getUserFullName()." am ".date("Y-m-d"));
    $tPatternNew = newTemplatePattern($ctrl, htmlspecialchars($newValue));
    echo "<div class=\"checkbox\"><label>";
    echo "<input type=\"checkbox\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    if ($isChecked)
      echo " checked=\"checked\"";
    if (in_array("required", $layout["opts"]))
      echo " required=\"required\"";
    if (in_array("toggleReadOnly", $layout["opts"]))
      echo " data-isToggleReadOnly=\"true\"";
    echo " value=\"{$tPatternNew}\"";
    echo "/>";
    echo $tPattern;
    echo "</label></div>";
  }
}

function renderFormItemText($layout, $ctrl) {
  global $nonce, $URIBASE, $attributes, $GremiumPrefix;

  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);
  $noFormMarkup |= $noFormCompress;
  $isWikiUrl = ($layout["type"] == "url" && in_array("wikiUrl", $layout["opts"]));
  $isDS = isset($layout["data-source"]);
  $isReloadFirst = in_array("refreshFormBeforeChange", $layout["opts"]);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } elseif (isset($layout["value"])) {
    $value = $layout["value"];
  } elseif (!$noForm && isset($layout["prefill"]) && $layout["prefill"] == "user:mail") {
    $value = getUserMail();
  }
  $tPattern = newTemplatePattern($ctrl, htmlspecialchars($value));

  $ctrl["_render"]->displayValue = htmlspecialchars($value);
  if (isset($layout["addToSum"])) {
    foreach ($layout["addToSum"] as $addToSumId) {
      $ctrl["_render"]->addToSumMeta[$addToSumId] = $layout;
      if (!isset($ctrl["_render"]->addToSumValue[$addToSumId]))
        $ctrl["_render"]->addToSumValue[$addToSumId] = 0.00;
      $ctrl["_render"]->addToSumValue[$addToSumId] += (float) $value;
    }
  }

  if (!$noForm && $ctrl["readonly"]) {
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }
  if (isset($layout["printSum"])) { # filter based on [data-printSum~={$printSumId}]
    $noForm = true;
  }

  if (!$noFormMarkup && $noForm) {
    echo "<div class=\"form-control\"";
  } elseif (!$noForm) {
    if ($isWikiUrl || $isDS) {
      $cls = ["input-group"];
      if ($isDS)
        $cls[] = "custom-combobox";
      echo "<div class=\"".htmlspecialchars(implode(" ",$cls))."\">";
    }
    $fType = $layout["type"];
    if ($fType == "iban")
      $fType = "text";
    if ($fType == "titelnr")
      $fType = "text";
    if ($fType == "kostennr")
      $fType = "text";
    if ($fType == "kontennr")
      $fType = "text";
    $cls = ["form-control"];
    if ($isReloadFirst)
      $cls[] = "reload-first";
    echo "<input class=\"".implode(" ", $cls)."\" type=\"{$fType}\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
  }

  if (isset($layout["addToSum"])) { # filter based on [data-addToSum~={$addToSumId}]
    echo " data-addToSum=\"".htmlspecialchars(printSumId($layout["addToSum"]))."\"";
  }
  if (isset($layout["printSum"])) { # filter based on [data-printSum~={$printSumId}]
    echo " data-printSum=\"".htmlspecialchars(printSumId($layout["printSum"]))."\"";
  }
  if ($layout["type"] == "iban") {
    echo " data-validateIBAN=\"1\"";
  }

  if ($noForm) {
    if (!$noFormMarkup) {
      echo ">";
    }
    if ($layout["type"] == "email" && !empty($value))
      echo "<a href=\"mailto:{$tPattern}\" class=\"link-shows-url\">";
    if ($layout["type"] == "url" && !empty($value))
      echo "<a href=\"{$tPattern}\" target=\"_blank\" class=\"link-shows-url\">";
    if (isset($layout["format"]))
      echo "<{$layout["format"]}>";
    echo $tPattern;
    if (isset($layout["format"]))
      echo "</{$layout["format"]}>";
    if ($layout["type"] == "email" && !empty($value))
      echo "</a>";
    if ($layout["type"] == "url" && !empty($value))
      echo "</a>";
    if (!$noFormMarkup) {
      echo "</div>";
    }
  } else {
    if (isset($layout["placeholder"]))
      echo " placeholder=\"".htmlspecialchars($layout["placeholder"])."\"";
    if (in_array("required", $layout["opts"]))
      echo " required=\"required\"";
    if (isset($layout["minLength"]))
      echo " data-minlength=\"".htmlspecialchars($layout["minLength"])."\"";
    if (isset($layout["maxLength"]))
      echo " maxlength=\"".htmlspecialchars($layout["maxLength"])."\"";
    if (isset($layout["pattern"]))
      echo " pattern=\"".htmlspecialchars($layout["pattern"])."\"";
    else if (isset($layout["pattern-from-prefix"])) {
      $pattern = hexEscape($layout["pattern-from-prefix"]).".*"; # preg_quote produces invalid \: result
      echo " pattern=\"".htmlspecialchars($pattern)."\"";
      echo " data-pattern-from-prefix=\"".htmlspecialchars($layout["pattern-from-prefix"])."\"";
    }
    if (isset($layout["pattern-error"]))
      echo " data-pattern-error=\"".htmlspecialchars($layout["pattern-error"])."\"";
    if ($layout["type"] == "email") {
      echo " data-remote=\"".htmlspecialchars(str_replace("//","/",$URIBASE."/")."validate.php?ajax=1&action=validate.email&nonce=".urlencode($nonce))."\"";
      echo " data-remote-error=\"Ungültige eMail-Adresse\"";
    } elseif ($layout["type"] == "url" && in_array("wikiUrl", $layout["opts"])) {
      echo " data-tree-url=\"".htmlspecialchars(str_replace("//","/",$URIBASE."/")."validate.php?ajax=1&action=propose.wiki&nonce=".urlencode($nonce))."\"";
      echo " data-remote=\"".htmlspecialchars(str_replace("//","/",$URIBASE."/")."validate.php?ajax=1&action=validate.wiki&nonce=".urlencode($nonce))."\"";
    }
    if (isset($layout["onClickFillFrom"]))
      echo " data-onClickFillFrom=\"".htmlspecialchars($layout["onClickFillFrom"])."\"";
    if (isset($layout["onClickFillFromPattern"]))
      echo " data-onClickFillFromPattern=\"".htmlspecialchars($layout["onClickFillFromPattern"])."\"";
    if ($isReloadFirst) {
      echo " data-oldValue=\"{$tPattern}\"";
    }
    echo " value=\"{$tPattern}\"";
    echo "/>";
    if ($isWikiUrl) {
      echo "<div class=\"input-group-btn dropdown-toggle\">";
      echo "<span></span>"; // for borders
      echo "<button class=\"btn btn-default tree-view-btn ".(in_array("hasFeedback", $layout["opts"]) ? "form-control":"")." dropdown-toggle tree-view-toggle\">";
      echo "<span class=\"caret mycaret-down tree-view-show\"></span>";
      echo "<i class=\"fa fa-spinner fa-spin tree-view-spinning\" style=\"font-size:20px\"></i>";
      echo "<span class=\"caret mycaret-up tree-view-hide\"></span>";
      echo "</button>";
      echo "</div>";
    }
    if ($isDS) {
      $dsId = $ctrl["id"]."-dataSource";
?>
     <ul id="<?php echo htmlspecialchars($dsId); ?>" class="dropdown-menu" role="menu">
<?php
       if ($layout["data-source"] == "own-orgs") {
         $gremien = $attributes["gremien"];
         if ($value != "" && !in_array($value, $attributes["gremien"]))
           $gremien[] = $value;
         sort($gremien, SORT_STRING | SORT_FLAG_CASE);
         $lastNotEmpty = false;
         foreach ($GremiumPrefix as $prefix) {
           $thisNotEmpty = false;
           foreach ($gremien as $gremium) {
             if (substr($gremium, 0, strlen($prefix)) != $prefix) continue;
             if ($lastNotEmpty) echo '<li role="separator" class="divider"></li>'; $lastNotEmpty = false;
             if (!$thisNotEmpty) echo '<li class="dropdown-header"><span class="text">'.$prefix.'</span></li>'; $thisNotEmpty = true;
             echo '<li><a class="opt" role="option" aria-disabled="false" aria-selected="false" value="';
             echo htmlspecialchars($gremium);
             echo '"><span class="text">';
             echo htmlspecialchars($gremium);
             echo '</span></a></li>';
           }
           $lastNotEmpty |= $thisNotEmpty;
         }
       }
       if ($layout["data-source"] == "own-mailinglists") {
         $mailinglists = $attributes["mailinglists"];
         if ($value != "" && !in_array($value, $attributes["mailinglists"]))
           $mailinglists[] = $value;
         sort($mailinglists, SORT_STRING | SORT_FLAG_CASE);
         foreach ($mailinglists as $mailinglist) {
           echo "<li class=\"input-xs\"><a href=\"#\" value=\"".htmlspecialchars($mailinglist)."\">";
           echo htmlspecialchars($mailinglist);
           echo "</a></li>";
         }
       }
?>
     </ul>
   <div class="input-group-btn custom-combobox dropdown-toggle" data-toggle="dropdown">
     <span></span> <!-- // for borders -->
     <button type="button" class="btn btn-default dropdown-toggle <?php if (in_array("hasFeedback", $layout["opts"])) echo "form-control"; ?>">
       <span class="caret"></span>
     </button>
   </div>
<?php
    }
    if ($isWikiUrl || $isDS)
      echo "</div>"; // input-group
    if (in_array("hasFeedback", $layout["opts"]))
      echo '<span class="glyphicon form-control-feedback" aria-hidden="true"></span>';
    if ($layout["type"] == "url" && in_array("wikiUrl", $layout["opts"]))
      echo '<div class="tree-view" aria-hidden="true" id="'.htmlspecialchars($ctrl["id"]).'-treeview"></div>';
  }
}

function renderFormItemMoney($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "0.00";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } elseif (isset($layout["value"])) {
    $value = $layout["value"];
  }
  $fvalue = convertDBValueToUserValue($value, $layout["type"]);
  $tPattern = newTemplatePattern($ctrl, htmlspecialchars($fvalue));
  $tPatternC = newTemplatePattern($ctrl, htmlspecialchars($layout["currency"]));
  $tPatternS = newTemplatePattern($ctrl, "Σ");

  $ctrl["_render"]->displayValue = htmlspecialchars($value);
  if (isset($layout["addToSum"])) {
    foreach ($layout["addToSum"] as $addToSumId) {
      $ctrl["_render"]->addToSumMeta[$addToSumId] = $layout;
      if (!isset($ctrl["_render"]->addToSumValue[$addToSumId]))
        $ctrl["_render"]->addToSumValue[$addToSumId] = 0.00;
      $ctrl["_render"]->addToSumValue[$addToSumId] += (float) $value;
    }
  }

  if (!$noForm && $ctrl["readonly"]) {
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }

  if (isset($layout["printSum"])) {
    $noForm = true;
  }
  if (isset($layout["printSumDefer"])) {
    $noForm = true;
    $refname = false;
    if ( $ctrl["_render"]->currentRowId !== false)
      $refname = $ctrl["_render"]->rowIdToNumber[ $ctrl["_render"]->currentRowId ];
    elseif ( $ctrl["_render"]->currentParent !== false )
      $refname = $ctrl["_render"]->currentParent;
    echo "<!-- refname=$refname -->";
    $ctrl["_render"]->postHooks[] = function($ctrl) use ($tPattern, $tPatternC, $tPatternS, &$layout, $refname, $noForm, $noFormMarkup, $noFormCompress) {
      $sums = [];
      if ($refname === false) {
        $sums = $ctrl["_render"]->addToSumValue;
      } elseif (isset($ctrl["_render"]->addToSumValueByRowRecursive[$refname])) {
        $sums = $ctrl["_render"]->addToSumValueByRowRecursive[$refname];
      }
      $psId = $layout["printSumDefer"];
      $src = [];
      $value = evalPrintSum($psId, $sums, $src);
      $value = number_format($value, 2, ".", "");
      if (in_array("hide-if-zero", $layout["opts"]) && $value == 0 && $noForm && ($noFormCompress || $noFormMarkup)) {
        $fvalue = "";
        $ctrl["_render"]->templates[$tPatternC] = "";
        $ctrl["_render"]->templates[$tPatternS] = "";
      } else
        $fvalue = convertDBValueToUserValue($value, $layout["type"]);
      $ctrl["_render"]->templates[$tPattern] = $fvalue;
    };
    $psId = $layout["printSumDefer"];
    if (!isset($ctrl["_render"]->addToSumMeta[$psId]))
      $ctrl["_render"]->addToSumMeta[$psId] = $layout;
  } else if (in_array("hide-if-zero", $layout["opts"]) && $value == 0 && $noForm && ($noFormCompress || $noFormMarup))
    return false;

  if (!($noFormMarkup || $noFormCompress) || !$noForm)
    echo "<div class=\"input-group\">";
  else
    echo "<div class=\"text-right\">";

  if (in_array("is-sum", $layout["opts"])) {
    if (!($noFormMarkup || $noFormCompress))
      echo "<span class=\"input-group-addon\">$tPatternS</span>";
    else
      echo "$tPatternS&nbsp;";
  }

  if ($noForm && ($noFormMarkup || $noFormCompress)) {
    echo "<div class=\"text-right visible-inline\"";
  } else if ($noForm) {
    echo "<div class=\"form-control text-right\"";
  } else {
    echo "<input type=\"text\" class=\"form-control text-right\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
  }

  if (isset($layout["addToSum"])) { # filter based on [data-addToSum~={$addToSumId}]
    echo " data-addToSum=\"".htmlspecialchars(printSumId($layout["addToSum"]))."\"";
  }
  if (isset($layout["printSum"])) { # filter based on [data-printSum~={$printSumId}]
    echo " data-printSum=\"".htmlspecialchars(printSumId($layout["printSum"]))."\"";
  }
  if ($noForm) {
    echo ">";
    echo $tPattern;
    echo "</div>";
  } else {
    if (in_array("required", $layout["opts"]))
      echo " required=\"required\"";
    echo " value=\"{$tPattern}\"";
    echo "/>";
  }

  if (!($noFormMarkup || $noFormCompress) || !$noForm)
    echo "<span class=\"input-group-addon\">$tPatternC</span>";
  else
    echo "&nbsp;$tPatternC";

  echo "</div>";
}

function renderFormItemTextarea($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } elseif (isset($layout["value"])) {
    $value = $layout["value"];
  }

  $ctrl["_render"]->displayValue = htmlspecialchars($value);

  if (!$noForm && $ctrl["readonly"]) {
    $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
    echo "<textarea style=\"display:none;\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\">";
    echo $tPattern;
    echo '</textarea>';
    $noForm = true;
  }

  if ($noForm && $noFormMarkup) {
    echo newTemplatePattern($ctrl, implode("<br/>",explode("\n",htmlspecialchars($value))));
  } elseif ($noForm) {
    echo "<div>";
    echo newTemplatePattern($ctrl, implode("<br/>",explode("\n",htmlspecialchars($value))));
    echo "</div>";
  } else {
    echo "<textarea class=\"form-control\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    if (isset($layout["min-rows"]))
      echo " rows=".htmlspecialchars($layout["min-rows"]);
    if (in_array("required", $layout["opts"]))
      echo " required=\"required\"";
    echo ">";
    echo newTemplatePattern($ctrl, htmlspecialchars($value));
    echo "</textarea>";
  }
}

function getFileLink($file, $antrag) {
  global $URIBASE;
  $target = str_replace("//","/",$URIBASE."/").rawurlencode($antrag["token"])."/anhang/".$file["id"];
  return "<a class=\"show-file-name\" href=\"".htmlspecialchars($target)."\">".htmlspecialchars($file["filename"])."</a>";
}

function renderFormItemFile($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $file = false;
  if (isset($ctrl["_values"])) {
    $file = getFormFile($ctrl["name"], $ctrl["_values"]["_anhang"]);
  }
  $html = "";
  $fileName = "";
  if ($file) {
    $fileName = $file["filename"];
    $html = getFileLink($file, $ctrl["_values"]);
  }
  $ctrl["_render"]->displayValue = $html;
  $tPattern = newTemplatePattern($ctrl, $html);

  if ($noForm) {
    echo "<div>";
    echo $tPattern;
    echo "</div>";
  } else {
    $oldFieldNameFieldName = "formdata[{$layout["id"]}][oldFieldName]";
    $oldFieldNameFieldNameOrig = $oldFieldNameFieldName;
    foreach($ctrl["suffix"] as $suffix) {
      $oldFieldNameFieldName .= "[{$suffix}]";
      $oldFieldNameFieldNameOrig .= "[]";
    }
    $oldFieldName = "<input type=\"hidden\" name=\"".htmlspecialchars($oldFieldNameFieldName)."\" orig-name=\"".htmlspecialchars($oldFieldNameFieldNameOrig)."\" id=\"".htmlspecialchars($ctrl["id"])."-oldFieldName\" value=\"".htmlspecialchars(getFormName($ctrl["name"]))."\"/>";

    $myOut = "<div class=\"single-file-container\">";
    $myOut .= "<input class=\"form-control single-file\" type=\"file\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"/>";
    $myOut .= $oldFieldName;
    $myOut .= "</div>";
    if ($file) {
      $renameFileFieldName = "formdata[{$layout["id"]}][newFileName]";
      $renameFileFieldNameOrig = $renameFileFieldName;
      foreach($ctrl["suffix"] as $suffix) {
        $renameFileFieldName .= "[{$suffix}]";
        $renameFileFieldNameOrig .= "[]";
      }

      echo "<div class=\"single-file-container\" data-display-text=\"".newTemplatePattern($ctrl, $fileName)."\" data-filename=\"".newTemplatePattern($ctrl, $fileName)."\" data-orig-filename=\"".newTemplatePattern($ctrl, $fileName)."\" data-old-html=\"".htmlspecialchars($myOut)."\">";
      echo "<span>".$tPattern."</span>";
      echo "<span>&nbsp;</span>";
      echo "<small><nobr class=\"show-file-size\">".newTemplatePattern($ctrl, $file["size"])."</nobr></small>";
      if (!$ctrl["readonly"]) {
        echo "<a href=\"#\" class=\"on-click-rename-file\"><i class=\"fa fa-fw fa-pencil\"></i></a>";
        echo "<a href=\"#\" class=\"on-click-delete-file\"><i class=\"fa fa-fw fa-trash\"></i></a>";
      }
      echo "<input type=\"hidden\" name=\"".htmlspecialchars($renameFileFieldName)."\" orig-name=\"".htmlspecialchars($renameFileFieldNameOrig)."\" id=\"".htmlspecialchars($ctrl["id"])."-newFileName\" value=\"\" class=\"form-file-name\"/>";
      echo $oldFieldName;
      echo "</div>";
    } elseif ($ctrl["readonly"]) {
      echo "<div class=\"single-file-container\">";
      echo "</div>";
    } else {
      echo $myOut;
    }
  }
}

function renderFormItemMultiFile($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  if ($noForm && isset($layout["destination"]))
    return false; // no data here

  if (!$noForm && $ctrl["readonly"] && isset($layout["destination"]))
    return false;

  $files = false;
  if (isset($ctrl["_values"])) {
    $files = getFormFiles($ctrl["name"], $ctrl["_values"]["_anhang"]);
  }
  $html = [];
  if (is_array($files)) {
    foreach($files as $file) {
      $html[] = getFileLink($file, $ctrl["_values"]);
    }
  }
  $ctrl["_render"]->displayValue = implode(", ",$html);

  if ($noForm) {
    if (isset($layout["destination"])) return false; // no data here

    echo "<div>";
    if (count($html) > 0) {
      echo newTemplatePattern($ctrl, "<ul><li>".implode("</li><li>",$html)."</li></ul>");;
    }
    echo "</div>";
    return;
  }

  echo "<div";
  if (isset($layout["destination"])) {
    $cls = ["multi-file-container", "multi-file-container-with-destination"];
    if (in_array("update-ref", $layout["opts"]))
      $cls[] = "multi-file-container-update-ref";
    $layout["destination"] = str_replace(".", "-", $layout["destination"]);

    echo " class=\"".implode(" ", $cls)."\"";
    echo " data-destination=\"".htmlspecialchars($layout["destination"])."\"";
  } else {
    echo " class=\"multi-file-container multi-file-container-without-destination\"";
  }
  echo ">";

  if (count($html) > 0) {
    echo "<ul>";
    foreach($files as $i => $file) {
      $oldFieldNameFieldName = "formdata[{$layout["id"]}][oldFieldName]";
      $oldFieldNameFieldNameOrig = $oldFieldNameFieldName;
      foreach($ctrl["suffix"] as $suffix) {
        $oldFieldNameFieldName .= "[{$suffix}]";
        $oldFieldNameFieldNameOrig .= "[]";
      }
      $oldFieldNameFieldName .= "[]";
      $oldFieldNameFieldNameOrig .= "[]";
      $oldFieldName = "<input type=\"hidden\" name=\"".htmlspecialchars($oldFieldNameFieldName)."\" orig-name=\"".htmlspecialchars($oldFieldNameFieldNameOrig)."\" id=\"".htmlspecialchars($ctrl["id"])."-oldFieldName\" value=\"".htmlspecialchars($file["fieldname"])."\"/>";

      $renameFileFieldName = "formdata[{$layout["id"]}][newFileName]";
      $renameFileFieldNameOrig = $renameFileFieldName;
      foreach($ctrl["suffix"] as $suffix) {
        $renameFileFieldName .= "[{$suffix}]";
        $renameFileFieldNameOrig .= "[]";
      }
      $renameFileFieldName .= "[]";
      $renameFileFieldNameOrig .= "[]";

      $fileName = $file["filename"];

      echo "<li class=\"multi-file-container-olddata-singlefile\" data-display-text=\"".newTemplatePattern($ctrl, $fileName)."\" data-filename=\"".newTemplatePattern($ctrl, $fileName)."\" data-orig-filename=\"".newTemplatePattern($ctrl, $fileName)."\">";
      echo "<span>".newTemplatePattern($ctrl, $html[$i])."</span>";
      echo "<span>&nbsp;</span>";
      echo "<small><nobr class=\"show-file-size\">".newTemplatePattern($ctrl, $file["size"])."</nobr></small>";
      if (!$ctrl["readonly"]) {
        echo "<a href=\"#\" class=\"on-click-rename-file\"><i class=\"fa fa-fw fa-pencil\"></i></a>";
        echo "<a href=\"#\" class=\"on-click-delete-file\"><i class=\"fa fa-fw fa-trash\"></i></a>";
      }
      echo "<input type=\"hidden\" name=\"".htmlspecialchars($renameFileFieldName)."\" orig-name=\"".htmlspecialchars($renameFileFieldNameOrig)."\" id=\"".htmlspecialchars($ctrl["id"])."-newFileName\" value=\"\" class=\"form-file-name\"/>";
      echo $oldFieldName;
      echo "</li>";
    }
    echo "</ul>";
  }

  if (!$ctrl["readonly"]) {
    echo "<input class=\"form-control multi-file\" type=\"file\" name=\"".htmlspecialchars($ctrl["name"])."[]\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\"[] id=\"".htmlspecialchars($ctrl["id"])."\" multiple";
    if (in_array("dir", $layout["opts"])) {
      echo " webkitdirectory";
    }
    echo "/>";
  }
  echo "</div>";
}

function getTrText($trId, $ctrl) {
  $matches = [];
  $origValue = $trId;

  if ($trId == "")
    return "";

  if (!preg_match('/^(.*)\{([0-9\-]+)\}$/', $trId, $matches)) {
    return newTemplatePattern($ctrl, htmlspecialchars("invalid row id: ".$trId));
  }

  if (!isset($ctrl["_render"])) {
    return newTemplatePattern($ctrl, htmlspecialchars("form not rendered: ".$trId));
  }

  $tableBaseName = $matches[1];
  $rowIdentifier = $matches[2];
  // rowIdentifier is stored in $tableBaseName[rowId]$suffix

  if (isset($ctrl["_values"])) {
    $ret = getFormEntries("{$tableBaseName}[rowId]", "table", $ctrl["_values"]["_inhalt"], $rowIdentifier);
    if (count($ret) == 0) {
      return newTemplatePattern($ctrl, htmlspecialchars("unknown row id: ".$trId));
    }
    if (count($ret) > 1) {
      return newTemplatePattern($ctrl, htmlspecialchars("non-unique row id: ".$trId));
    }
    $trName = str_replace("[rowId]", "", $ret[0]["fieldname"]);
  } else {
    return newTemplatePattern($ctrl, htmlspecialchars("missing formdata to resolve row id: ".$trId));
  }

  if (!preg_match('/^(.*)\[([0-9]+)\]$/', $trName, $matches)) {
    return newTemplatePattern($ctrl, htmlspecialchars("miss row idx: ".$trName));
  }

  $currentTable = $matches[1];
  $value = $matches[1];
  $currentRow = (int) $matches[2];

  $txtTr = [ "[$currentRow] <{rowTxt:".$currentTable."[".$currentRow."]}>" ];
  while (preg_match('/^(.*)\[([0-9]+)\]$/', $value, $matches)) {
    if (!isset($ctrl["_render"]->parentMap[$currentTable])) {
      echo "$origValue evaluated to $currentTable which has no parent<br/>\n";
      echo "<pre>";print_r($ctrl["_render"]->parentMap); echo"</pre>\n";
      break;
    }
    $currentTable = $ctrl["_render"]->parentMap[$currentTable];
    $currentRow = (int) $matches[2];
    $value = $matches[1];
    if (!isset($ctrl["_render"]->templates["<{rowTxt:".$currentTable."[".$currentRow."]}>"])) {
      echo "$origValue evaluated to $currentTable and $currentRow which has no text<br/>\n";
      echo "<pre>";print_r($ctrl["_render"]->templates); echo"</pre>\n";
    } else { /* might not be a table */
      array_unshift($txtTr, "[$currentRow] <{rowTxt:".$currentTable."[".$currentRow."]}>");
    }
  }

  return implode(" ", $txtTr);
}

function renderFormItemSelect($layout, $ctrl) {
  global $attributes, $GremiumPrefix;

  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  }
  if ($layout["type"] == "ref" && is_array($layout["references"]) && isset($layout["refValueIfEmpty"]) && $value == "" && isset($ctrl["_values"]) && isset($ctrl["_values"]["_inhalt"])) {
    $fvalue = getFormValueInt($layout["refValueIfEmpty"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  } else {
    $fvalue = $value;
  }
  if ($layout["type"] == "ref") {
    $rowId = false;
    if (is_array($layout["references"])) {
      # skip referencesId: Projektgenehmigungen beziehen sich auf einen HHP, sind aber auch im nächsten HHP noch gültig.
      # referencesKey ist dort auflösbar, rowId kann aber verschieden sein.
      # referencesId wird in dem Fall dennoch benötigt, wenn der Antrag nochmal gedruckt oder gelesen werden soll aber im aktuellen HHP der Titel nicht mehr existiert o.ä.
      $useReferencesId = $noForm || !in_array("edit-skip-referencesId", $layout["opts"]); # is readonly or edit-skip-referencesId is not set
      if (isset($layout["referencesId"]) && !in_array("skip-referencesId", $ctrl["render"]) && $useReferencesId ) {
        $otherFormIdField = "formdata[{$layout["referencesId"]}]";
        /* rationale:otherFormIdField uses no suffix as 
         * 1. current logic ensures it always references the same form on every copy
         * 2. it would make checking references more difficult
         */
        $otherFormId = "";
        if (isset($ctrl["_values"]))
          $otherFormId = getFormValue($otherFormIdField, "otherForm", $ctrl["_values"]["_inhalt"], $otherFormId);
        if ($otherFormId != "")
          $layout["references"][0] = "id:{$otherFormId}";
      }
      $tmp = otherForm($layout, $ctrl, "no-nesting");
      $txtTr = "";
      if ($tmp !== false) {
        $otherForm = $tmp["form"];
        $otherCtrl = $tmp["ctrl"];
        $otherAntrag = $tmp["antrag"];
  
        $rowId = false;
        if (isset($layout["referencesKey"])) {
          $rowIdentifier = false;
          foreach (array_keys($layout["referencesKey"]) as $tableName) {
            $ret = getFormEntries($layout["referencesKey"][$tableName], null, $otherCtrl["_values"]["_inhalt"], $fvalue);
            if (count($ret) != 1)
              continue;
            $suffix = substr($ret[0]["fieldname"], strlen($layout["referencesKey"][$tableName]));
            $rowIdentifier = getFormValueInt("{$tableName}[rowId]{$suffix}", null, $otherCtrl["_values"]["_inhalt"], false);
            if ($rowIdentifier === false)
              continue;
            $rowId = "{$tableName}{{$rowIdentifier}}";
            break;
          }
        } else if ($fvalue != "") {
          $rowId = $fvalue;
        }
      }
    } else if ($fvalue != "") {
      $rowId = $fvalue;
    }
    if ($rowId !== false && $rowId != "" && !in_array("no-invref", $layout["opts"]) ) {
      $ctrl["_render"]->referencedBy[$rowId][] = $ctrl["_render"]->currentParent."[".$ctrl["_render"]->currentParentRow."]";
    }
  }

  if (!$noForm && $ctrl["readonly"]) {
    $tPattern =  newTemplatePattern($ctrl, htmlspecialchars($value));
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }

  if ($noForm) {
    if (isset($layout["data-source"]) && in_array($layout["data-source"], [ "own-orgs", "own-mailinglists" ]) && $layout["type"] != "ref") {
      if ($noFormMarkup)
        echo "<div class=\"visible-inline\">";
      else
        echo "<div class=\"form-control\">";
      echo newTemplatePattern($ctrl, htmlspecialchars($value));
      if ($value == "")
        echo "&nbsp;"; # prevent collapsing
      echo "</div>";
      $ctrl["_render"]->displayValue = htmlspecialchars($value);
    } else if ($layout["type"] == "ref" && is_array($layout["references"])) {
      if ($rowId === false || $rowId == "") {
        if ($value != "")
          $txtTr = "missing row id for ".htmlspecialchars($value);
        else
          $txtTr = "";
      } else {
        $txtTr = getTrText($rowId, $otherCtrl);
        $txtTr = processTemplates($txtTr, $otherCtrl); // rowTxt is from displayValue and thus already escaped
      }

      $tPattern = newTemplatePattern($ctrl, $txtTr);
      echo "<div>";
      echo $tPattern;
      if ($txtTr == "")
        echo "&nbsp;"; # prevent collapsing
      echo "</div>";
    } else if ($layout["type"] == "ref") {
      $tPattern = newTemplatePattern($ctrl, htmlspecialchars("<{ref:$value}>"));
      echo "<div>";
      echo $tPattern;
      echo "</div>";
      $ctrl["_render"]->postHooks[] = function($ctrl) use ($tPattern, $value) {
        $txtTr = getTrText($value, $ctrl);
        $txtTr = processTemplates($txtTr, $ctrl); // rowTxt is from displayValue and thus already escaped
        if ($txtTr == "")
          $txtTr = "&nbsp;";
        $ctrl["_render"]->templates[$tPattern] = $txtTr;
      };
    } else {
      echo "<div class=\"form-control\">";
      echo "**not implemented**";
      echo "</div>";
    }
    return;
  }

  $liveSearch = true;
  if (isset($layout["data-source"]) && $layout["data-source"] == "own-orgs")
    $liveSearch = false;

  $cls = ["select-picker-container"];
  if (in_array("hasFeedback", $layout["opts"]))
    $cls[] = "hasFeedback";
  echo "<div class=\"".implode(" ", $cls)."\">";
  if (in_array("hasFeedback", $layout["opts"]))
    echo '<span class="glyphicon form-control-feedback" aria-hidden="true"></span>';
  echo "<select class=\"selectpicker form-control\" data-live-search=\"".($liveSearch ? "true" : "false")."\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
  if (isset($layout["placeholder"]))
    echo " title=\"".htmlspecialchars($layout["placeholder"])."\"";
  elseif ($layout["type"] == "ref")
    echo " title=\"".htmlspecialchars("Bitte auswählen")."\"";
  if (in_array("multiple", $layout["opts"]))
    echo " multiple";
  if (in_array("required", $layout["opts"]))
    echo " required=\"required\"";
  if ($layout["type"] == "ref" && is_string($layout["references"])) {
    $layout["references"] = str_replace(".", "-", $layout["references"]);
    echo " data-references=\"".htmlspecialchars($layout["references"])."\"";
  }
  if ($layout["type"] == "ref" && is_array($layout["references"]) && isset($layout["updateByReference"])) {
    echo " data-update-value-maps=\"present\"";
  }
  if ($value != "") {
    $tPattern = newTemplatePattern($ctrl, htmlspecialchars($value));
    echo " data-value=\"{$tPattern}\"";
  }
  echo ">";

  if (isset($layout["data-source"]) && $layout["data-source"] == "own-orgs" && $layout["type"] != "ref") {
    $gremien = $attributes["gremien"];
    if ($value != "" && !in_array($value, $attributes["gremien"]))
      $gremien[] = $value;
    sort($gremien, SORT_STRING | SORT_FLAG_CASE);
    foreach ($GremiumPrefix as $prefix) {
      echo "<optgroup label=\"".htmlspecialchars($prefix)."\">";
      foreach ($gremien as $gremium) {
        if (substr($gremium, 0, strlen($prefix)) != $prefix) continue;
        echo "<option>".htmlspecialchars($gremium)."</option>";
      }
      echo "</optgroup>";
    }
  }
  if (isset($layout["data-source"]) && $layout["data-source"] == "own-mailinglists" && $layout["type"] != "ref") {
    $mailinglists = $attributes["mailinglists"];
    if ($value != "" && !in_array($value, $attributes["mailinglists"]))
      $mailinglists[] = $value;
    sort($mailinglists, SORT_STRING | SORT_FLAG_CASE);
    foreach ($mailinglists as $mailinglist) {
      echo "<option>".htmlspecialchars($mailinglist)."</option>";
    }
  }
  if ($layout["type"] == "ref")
    echo "<option value=\"\">Bitte auswählen</option>";
  if ($layout["type"] == "ref" && is_array($layout["references"])) {
    list ($txt, $otherFormId) = otherFormTrOptions($layout, $ctrl);
    echo $txt;
  }

  echo "</select>";
  if ($layout["type"] == "ref" && is_array($layout["references"]) && isset($layout["referencesId"])) {
    $otherFormIdField = "formdata[{$layout["referencesId"]}]";
    $otherFormIdTypeField = "formtype[{$layout["referencesId"]}]";
    /* rationale:otherFormIdField uses no suffix as 
     * 1. current logic ensures it always references the same form on every copy
     * 2. it would make checking references more difficult
     */
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($otherFormIdField)."\" value=\"".htmlspecialchars($otherFormId)."\">";
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($otherFormIdTypeField)."\" value=\"otherForm\">";
  }
  echo "</div>";
}

function renderOtherAntrag($antragId, &$ctrl, $renderOpts = "") {
  static $cache = false;
  if ($cache === false) $cache = [];
  $renderOpts = explode(",", $renderOpts);

  if (isset($ctrl["render"]) && in_array("no-nesting", $ctrl["render"])) return false;
  if (isset($ctrl["render"]) && in_array("no-form-compress", $ctrl["render"]))
    $renderOpts[] = "no-form-compress";

  $renderOpts = array_unique($renderOpts);
  sort($renderOpts);
  $renderOpts = implode(",", $renderOpts);

  $key = "a:{$antragId},r:{$renderOpts}";

  if (!isset($cache[$key])) {
    $otherAntrag = getAntrag($antragId);
    if ($otherAntrag === false) return false; # not readable. Ups.
    $otherForm =  getForm($otherAntrag["type"], $otherAntrag["revision"]);
    $otherCtrl = ["_values" => $otherAntrag, "render" => explode(",", "no-form,{$renderOpts}")];

    ob_start();
    $success = renderFormImpl($otherForm, $otherCtrl);
    ob_end_clean();

    if ($success)
      $cache[$key] = ["form" => $otherForm, "ctrl" => $otherCtrl, "antrag" => $otherAntrag];
  } else {
    $otherForm = $cache[$key]["form"];
    $otherCtrl = $cache[$key]["ctrl"];
    $otherAntrag = $cache[$key]["antrag"];
  }

  return ["form" => $otherForm, "ctrl" => $otherCtrl, "antrag" => $otherAntrag ];
}

function otherForm(&$layout, &$ctrl, $renderOpts = "") {
  $fieldValue = false;
  $fieldName = false;
  if (is_array($layout["references"][0])) {
    $formFilterDef = $layout["references"][0];
    $f = ["type" => $formFilterDef["type"]];
    if (isset($formFilterDef["state"]))
      $f["state"] = $formFilterDef["state"];
    if (isset($formFilterDef["revision"]))
      $f["revision"] = $formFilterDef["revision"];
    if (isset($formFilterDef["revisionIsYearFromField"])) {
      if (isset($ctrl["_values"]) && isset($ctrl["_values"]["_inhalt"])) {
        $fieldValue = getFormValueInt($formFilterDef["revisionIsYearFromField"], null, $ctrl["_values"]["_inhalt"], "");
        if (!empty($fieldValue)) {
          $year = substr($fieldValue,0,4);
          $f["revision"] = $year;
        }
      }
    }
    $al = dbFetchAll("antrag", $f);
    $currentFormId = false;
    if (isset($ctrl["_values"])) {
      $currentFormId = $ctrl["_values"]["id"];
    }
    $fieldValue = [];

    foreach ($al as $a) {
      if (isset($formFilterDef["referenceFormField"])) {
        $r = dbGet("inhalt", ["antrag_id" => $a["id"], "fieldname" => $formFilterDef["referenceFormField"], "contenttype" => "otherForm" ]);
        if ($r === false || $r["value"] != $currentFormId) continue;
      }
      $fieldValue[] = $a["id"];
    }
    if (count($fieldValue) != 1)
      $fieldValue = false;
    else
      $fieldValue = $fieldValue[0];
  } elseif ($layout["references"][0] == "referenceField") {
    if (!isset($ctrl["_config"]["referenceField"])) {
      return false; #no such field
    }
    $fieldName = $ctrl["_config"]["referenceField"]["name"];
  } elseif (substr($layout["references"][0],0,6) == "field:") {
    $fieldName = substr($layout["references"][0],6);
  } elseif (substr($layout["references"][0],0,3) == "id:") {
    $fieldValue = substr($layout["references"][0],3);
  } else {
    die("Unknown otherForm reference in references: {$layout["references"][0]}");
  }
  if ($fieldValue === false && $fieldName !== false && isset($ctrl["_values"]) && isset($ctrl["_values"]["_inhalt"]))
    $fieldValue = getFormValueInt($fieldName, null, $ctrl["_values"]["_inhalt"], $fieldValue);
  if ($fieldValue === false || $fieldValue == "") {
    return false; # nothing given here
  }
  $fieldValue = (int) $fieldValue;

  return renderOtherAntrag($fieldValue, $ctrl, $renderOpts);
}

function otherFormTrOptions($layout, $ctrl) {
  $tmp = otherForm($layout, $ctrl, "no-nesting");
  if ($tmp === false) return "";
  $otherForm = $tmp["form"];
  $otherCtrl = $tmp["ctrl"];
  $otherAntrag = $tmp["antrag"];

  $tableNames = $layout["references"][1];
  if (!isset($otherCtrl["_render"])) {
    return "Rendering skipped due to nesting";
  }

  if (!is_array($tableNames)) $tableNames = [ $tableNames => $tableNames ];
  $ret = "";

  foreach ($tableNames as $tableName => $label) {
    if (!isset($otherCtrl["_render"]->numTableRows[$tableName]))
      continue;

    if (count($tableNames) > 1) {
      $ret .= "<optgroup label=\"".htmlspecialchars($label)."\">";
    }

    foreach ($otherCtrl["_render"]->numTableRows[$tableName] as $suffix => $rowCount) {
#     $ret .= "\n<!-- row count $tableName : $rowCount -->";
      for($i=0; $i < $rowCount; $i++) {
        if (!isset($otherCtrl["_values"])) {
          $rowId = false;
          $rowKey = false;
        } else {
          $rowId = getFormValueInt("{$tableName}[rowId]{$suffix}[{$i}]", null, $otherCtrl["_values"]["_inhalt"], false);
          if (isset($layout["referencesKey"]) && isset($layout["referencesKey"][$tableName]))
            $rowKey = getFormValueInt("{$layout["referencesKey"][$tableName]}{$suffix}[{$i}]", null, $otherCtrl["_values"]["_inhalt"], false);
          else
            $rowKey = "{$tableName}{{$rowId}}";
        }
        if ($rowId !== false) {
          $txtTr = getTrText("{$tableName}{{$rowId}}", $otherCtrl);
          $txtTr = processTemplates($txtTr, $otherCtrl); // rowTxt is from displayValue and thus already escaped ;; pattern stored in otherRenderer thus copy
        } else {
          $txtTr = "missing {$tableName}[rowId]{$suffix}[{$i}]";
        }
        $tPattern = newTemplatePattern($ctrl, $txtTr);

        $updateByReference = [];
        if (isset($layout["updateByReference"]))
          $updateByReference = $layout["updateByReference"];
        $updateValueMap = [];
        foreach ($updateByReference as $destFieldName => $sources) {
          /* we only care for destFieldName with same suffix */
          $destFieldNameOrig = $destFieldName;
          foreach($ctrl["suffix"] as $s) {
            $destFieldName .= "[{$s}]";
            $destFieldNameOrig .= "[]";
          }
          $otherFormFieldValue = "";
          foreach ($sources as $srcFieldId) {
            $currSuffix = "{$suffix}[{$i}]";
            while ($currSuffix !== false) {
              $srcFieldName = $srcFieldId . $currSuffix;
  
              $m = [];
              if (!preg_match('/(.*)(\[[^[]]*\])$/', $currSuffix, $m))
                $currSuffix = false;
              else
                $currSuffix = $m[1];
  
              $fieldValue = getFormValueInt($srcFieldName, null, $otherAntrag["_inhalt"], false);
              if ($fieldValue === false) continue; /* other form does not have this field */
              if ($fieldValue == "") continue; /* other form left this field empty */
              /* if found */
               $otherFormFieldValue = $fieldValue;
              break 2; /* while currSuffix, foreach sources */
            }
          }
  
          $updateValueMap[ $destFieldNameOrig ] = $otherFormFieldValue;
        }
        $ret .= "<option value=\"".htmlspecialchars($rowKey)."\" data-update-value-map=\"".htmlspecialchars(json_encode($updateValueMap))."\">{$tPattern}</option>";
      }
    }

    if (count($tableNames) > 1) {
      $ret .= "</optgroup>";
    }

  }

  if ($otherAntrag !== false)
    $otherFormId = $otherAntrag["id"];
  else
    $otherFormId = false;

  return [ $ret, $otherFormId ];
}

function renderFormItemDateRange($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $valueStart = "";
  $valueEnd = "";
  if (isset($ctrl["_values"])) {
    $valueStart = getFormValue($ctrl["name"]."[start]", $layout["type"], $ctrl["_values"]["_inhalt"], $valueStart);
    $valueEnd = getFormValue($ctrl["name"]."[end]", $layout["type"], $ctrl["_values"]["_inhalt"], $valueEnd);
  }
  $tPatternStart = newTemplatePattern($ctrl, htmlspecialchars($valueStart));
  $tPatternEnd =  newTemplatePattern($ctrl, htmlspecialchars($valueEnd));
  $ctrl["_render"]->displayValue = htmlspecialchars("$valueStart - $valueEnd");

  if (!$noForm && $ctrl["readonly"]) {
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."[start]\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."[start]\" value=\"{$tPatternStart}\">";
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."[end]\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."[end]\" value=\"{$tPatternEnd}\">";
    $noForm = true;
  }

  if ($noForm && !$noFormMarkup) {
    echo '<div class="input-daterange input-group">';
    echo '<div class="input-group-addon" style="background-color: transparent; border: none;">von</div>';
    echo "<div class=\"form-control\">{$tPatternStart}</div>";
    echo '<div class="input-group-addon" style="background-color: transparent; border: none;">bis</div>';
    echo "<div class=\"form-control\">{$tPatternEnd}</div>";
    echo "</div>";
    return;
  } else if ($noForm && $noFormMarkup) {
    echo "<div class=\"visible-inline\">";
    if ($valueStart != "") {
      echo ' von ';
      echo "{$tPatternStart}";
    }
    if ($valueEnd != "") {
      echo ' bis ';
      echo "{$tPatternEnd}";
    }
    echo "</div>";
    return;
  }

?>
    <div class="input-daterange input-group"
         data-provide="datepicker"
         data-date-format="yyyy-mm-dd"
         data-date-calendar-weeks="true"
         data-date-language="de"
<?php
  if (in_array("not-before-creation", $layout["opts"])) {
?>
         data-date-start-date="today"
<?php
  }
?>
    >
        <div class="input-group-addon" style="background-color: transparent; border: none;">
          von
        </div>
        <div class="input-group">
          <input type="text"
                 class="input-sm form-control"
                 name="<?php echo htmlspecialchars($ctrl["name"]); ?>[start]"
                 orig-name="<?php echo htmlspecialchars($ctrl["orig-name"]); ?>[start]"
                 <?php echo (in_array("required", $layout["opts"]) ? "required=\"required\"": ""); ?>
                 <?php echo ($ctrl["readonly"] ? "readonly=\"readonly\"": ""); ?>
                 value="<?php echo $tPatternStart; ?>"
          />
          <div class="input-group-addon">
            <span class="glyphicon glyphicon-th"></span>
          </div>
        </div>
        <div class="input-group-addon" style="background-color: transparent; border: none;">
          bis
        </div>
        <div class="input-group">
          <input type="text"
                 class="input-sm form-control"
                 name="<?php echo htmlspecialchars($ctrl["name"]); ?>[end]"
                 orig-name="<?php echo htmlspecialchars($ctrl["orig-name"]); ?>[end]"
                 <?php echo (in_array("required", $layout["opts"]) ? "required=\"required\"": ""); ?>
                 <?php echo ($ctrl["readonly"] ? "readonly=\"readonly\"": ""); ?>
                 value="<?php echo $tPatternEnd; ?>"
          />
          <div class="input-group-addon">
            <span class="glyphicon glyphicon-th"></span>
          </div>
        </div>
    </div>
<?php

}


function renderFormItemDate($layout, $ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $value = "";
  if (isset($ctrl["_values"])) {
    $value = getFormValue($ctrl["name"], $layout["type"], $ctrl["_values"]["_inhalt"], $value);
  }
  $tPattern = newTemplatePattern($ctrl, htmlspecialchars($value));
  $ctrl["_render"]->displayValue = htmlspecialchars($value);

  if (!$noForm && $ctrl["readonly"]) {
    echo "<input type=\"hidden\" name=\"".htmlspecialchars($ctrl["name"])."\" orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\" id=\"".htmlspecialchars($ctrl["id"])."\"";
    echo " value=\"{$tPattern}\"";
    echo '>';
    $noForm = true;
  }

  if ($noForm) {
    $cls = [];
    if (!$noFormMarkup)
      $cls[] = "form-control";
    else
      $cls[] = "visible-inline";
    echo "<div class=\"".htmlspecialchars(implode(" ", $cls))."\">";
    echo $tPattern;
    echo "</div>";
    return;
  }

?>
<div class="input-group date"
     data-provide="datepicker"
     data-date-format="yyyy-mm-dd"
     data-date-calendar-weeks="true"
     data-date-language="de"
<?php
  if (in_array("not-before-creation", $layout["opts"])) {
?>
     data-date-start-date="today"
<?php
  }
?>
>
    <input type="text"
           class="form-control"
           name="<?php echo htmlspecialchars($ctrl["name"]); ?>"
           orig-name="<?php echo htmlspecialchars($ctrl["orig-name"]); ?>"
           id="<?php echo htmlspecialchars($ctrl["id"]); ?>"
           <?php echo (in_array("required", $layout["opts"]) ? "required=\"required\"": ""); ?>
           <?php echo (in_array("readonly", $layout["opts"]) ? "readonly=\"readonly\"": ""); ?>
           value="<?php echo $tPattern; ?>"
<?php
    if (isset($layout["onClickFillFrom"]))
      echo " data-onClickFillFrom=\"".htmlspecialchars($layout["onClickFillFrom"])."\"";
    if (isset($layout["onClickFillFromPattern"]))
      echo " data-onClickFillFromPattern=\"".htmlspecialchars($layout["onClickFillFromPattern"])."\"";
?>
    />
    <div class="input-group-addon">
        <span class="glyphicon glyphicon-th"></span>
    </div>
</div>
<?php
}

function renderFormItemTable($layout, $ctrl) {
  $withRowNumber = in_array("with-row-number", $layout["opts"]);
  $withHeadline = in_array("with-headline", $layout["opts"]);
  $withExpand = in_array("with-expand", $layout["opts"]);
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $cls = ["table", "table-striped", "summing-table"];
  if (!$noForm)
    $cls[] = "dynamic-table";
  if (in_array("fixed-width-table", $layout["opts"]))
    $cls[] = "fixed-width-table";
  if ($ctrl["readonly"] || $noForm)
    $cls[] = "dynamic-table-readonly";

  $rowCountFieldName =  "formdata[{$layout["id"]}][rowCount]";
  $rowCountFieldNameOrig = $rowCountFieldName;
  $rowCountFieldTypeName = "formtype[{$layout["id"]}]";
  $extraColsFieldName =  "formdata[{$layout["id"]}][extraCols]";
  $extraColsFieldNameOrig = $extraColsFieldName;
  $extraColsFieldTypeName = "formtype[{$layout["id"]}]";
  $rowIdCountFieldName =  "formdata[{$layout["id"]}][rowIdCount]";
  $rowIdCountFieldNameOrig = $rowIdCountFieldName;
  $rowIdCountFieldTypeName = "formtype[{$layout["id"]}]";
  $rowIdFieldName =  "formdata[{$layout["id"]}][rowId]";
  $rowIdFieldNameOrig = $rowIdFieldName;
  $rowIdFieldTypeName = "formtype[{$layout["id"]}]";
  foreach($ctrl["suffix"] as $suffix) {
    $rowCountFieldName .= "[{$suffix}]";
    $rowCountFieldNameOrig .= "[]";
    $extraColsFieldName .= "[{$suffix}]";
    $extraColsFieldNameOrig .= "[]";
    $rowIdCountFieldName .= "[{$suffix}]";
    $rowIdCountFieldNameOrig .= "[]";
  }

  $rowCount = 0;
  if (isset($ctrl["_values"])) {
    $rowCount = (int) getFormValue($rowCountFieldName, $layout["type"], $ctrl["_values"]["_inhalt"], $rowCount);
  }
  if ($noForm && $rowCount == 0) return false; //empty table

  $myParent = $ctrl["_render"]->currentParent;
  $myParentRow = $ctrl["_render"]->currentParentRow;
  if ($myParent !== false)
    $ctrl["_render"]->parentMap[getFormName($ctrl["name"])] = $myParent;
  $ctrl["_render"]->currentParent = getFormName($ctrl["name"]);

  $hasPrintSumFooter = false;
  list ($a, $b) = getFormNames($ctrl["name"]);
  $ctrl["_render"]->numTableRows[$a][$b] = $rowCount;

  $rowIdCount = 0;
  if (isset($ctrl["_values"])) {
    $rowIdCount = (int) getFormValue($rowIdCountFieldName, $layout["type"], $ctrl["_values"]["_inhalt"], $rowIdCount);
  }

?>

  <table class="<?php echo implode(" ", $cls); ?>" id="<?php echo htmlspecialchars($ctrl["id"]); ?>" orig-id="<?php echo htmlspecialchars($ctrl["orig-id"]); ?>" name="<?php echo htmlspecialchars($ctrl["name"]); ?>" orig-name="<?php echo htmlspecialchars($ctrl["orig-name"]); ?>">

<?php
  if (!$noForm) {
    echo "<input type=\"hidden\" value=\"".htmlspecialchars($rowCount)."\" name=\"".htmlspecialchars($rowCountFieldName)."\" orig-name=\"".htmlspecialchars($rowCountFieldNameOrig)."\" class=\"store-row-count\"/>";
    echo "<input type=\"hidden\" value=\"".htmlspecialchars($layout["type"])."\" name=\"".htmlspecialchars($rowCountFieldTypeName)."\"/>";
    echo "<input type=\"hidden\" value=\"".htmlspecialchars($rowIdCount)."\" name=\"".htmlspecialchars($rowIdCountFieldName)."\" orig-name=\"".htmlspecialchars($rowIdCountFieldNameOrig)."\" class=\"store-row-id-count\"/>";
    echo "<input type=\"hidden\" value=\"".htmlspecialchars($layout["type"])."\" name=\"".htmlspecialchars($rowIdCountFieldTypeName)."\"/>";
  }

  $compressableColumns = [];
  foreach ($layout["columns"] as $i => $col) {
    $layout["columns"][$i]["_hideable_isHidden"] = false;
    if (!isset($col["opts"]) || !in_array("hideable", $col["opts"]))
      continue;
    $name = "[$i]";
    if (isset($col["name"]))
      $name = $col["name"];
    $colId = $col["id"];
    $fname = $extraColsFieldName . "[" . $colId . "]";
    $fnameOrig = $extraColsFieldNameOrig . "[" . $colId . "]";
    if (isset($ctrl["_values"])) {
      $value = getFormValue($fname, null, $ctrl["_values"]["_inhalt"], ""); # checkbox does not store value if unchecked
    } else {
      $value = "show"; # default to show
    }
    $isChecked = ($value == "show");
    $compressableColumns[] = ["name" => $name, "i" => $i, "fname" => $fname, "fnameOrig" => $fnameOrig, "isChecked" => $isChecked ];
    $layout["columns"][$i]["_hideable_isHidden"] = !$isChecked;
  }

  $withHeadlineRow = $withHeadline || (count($compressableColumns) > 0);

  if ($withHeadlineRow) {

?>

    <thead>
      <tr>
<?php
        $colSpan = 0;
        if (!$noForm)
          $colSpan++; # delete-row
        if ($withRowNumber)
          $colSpan++;
        if ($withExpand)
          $colSpan++;
        if ($colSpan > 0)
          echo "<th colspan=\"{$colSpan}\">";
        if (count($compressableColumns) > 0 && !$noForm) {
          echo "<input type=\"hidden\" value=\"".htmlspecialchars($layout["type"])."\" name=\"".htmlspecialchars($extraColsFieldTypeName)."\"/>";
?>
          <div class="dropdown">
            <button type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <span class="caret"></span>
            </button>
            <ul class="dropdown-menu">
<?php

    foreach ($compressableColumns as $m) {
      $i = $m["i"];
      $name = $m["name"];
      $fname = $m["fname"];
      $fnameOrig = $m["fnameOrig"];
      $isChecked = $m["isChecked"];
?>
              <li><a href="javascript:void(false);" class="toggle-checkbox">
                  <input type="checkbox"
                         name="<?php echo htmlspecialchars($fname); ?>"
                         orig-name="<?php echo htmlspecialchars($fnameOrig); ?>"
                         <?php if ($isChecked) echo "checked=\"checked\""; ?>
                         data-col-class="dynamic-table-col-<?php echo htmlspecialchars($i); ?>"
                         class="col-toggle"
                         value="show" >
                  <?php echo htmlspecialchars($name); ?>
                  </a>
              </li>
<?php
    }

?>
            </ul>
          </div>
<?php
        }
        echo "</th>";
        foreach ($layout["columns"] as $i => $col) {
          if (isset($col["editWidth"]) && !$noForm)
            $col["width"] = $col["editWidth"];
          if (isset($col["width"]) && $col["width"] == -1)
            continue;
          if (!$noForm && isset($col["opts"]) && in_array("hide-edit", $col["opts"]))
            continue;
          $cls = [ "dynamic-table-cell", "dynamic-table-col-$i" ];
          if ($layout["columns"][$i]["_hideable_isHidden"])
            $cls[] = "hide-column-manual";
          if ($col["type"] == "money")
            $cls[] = "text-right";
          echo "<th class=\"".implode(" ", $cls)."\">";
          if ($withHeadline) {
            if ($col["name"] === true) {
              if ($col["type"] == "group") {
                $colWidthSum = 0;
                foreach ($col["children"] as $child) {
                  if (isset($child["editWidth"]) && !$noForm)
                    $child["width"] = $child["editWidth"];
                  if (isset($child["width"]) && $child["width"] == -1)
                    continue;
                  if (!$noForm && isset($child["opts"]) && in_array("hide-edit", $child["opts"]))
                    continue;
                  $title = (isset($child["title"]) ? $child["title"] : ( isset($child["name"]) ? $child["name"] : "{$child["id"]}") );
                  $childCls = [ "dynamic-table-caption" ];
                  if ($child["type"] == "money")
                    $childCls[] = "text-right";
                  if (isset($child["width"])) {
                    $colWidthSum += $child["width"];
                    $childCls[] = "col-xs-{$child["width"]}";
                  } else {
                    $colWidthSum += 1;
                  }
                  if ($colWidthSum > 12) break;
                  echo "<span class=\"".implode(" ", $childCls)."\">".htmlspecialchars($title)."</span>";
                }
              } elseif( isset ($col["title"])) {
                echo "<span class=\"dynamic-table-caption\">".htmlspecialchars($col["title"])."</span>";
              }
            } else {
              echo "<span class=\"dynamic-table-caption\">".htmlspecialchars($col["name"])."</span>";
            }
          }
          echo "</th>";
        }
?>
      </tr>
    </thead>

<?php
  }

?>
    <tbody>
<?php
     $addToSumValueBeforeTable = $ctrl["_render"]->addToSumValue;
     if (!$noForm)
       $rowCountPrint = $rowCount+1;
     else
       $rowCountPrint = $rowCount;

     for ($rowNumber = 0; $rowNumber < $rowCountPrint; $rowNumber++) { # this prints $rowCount +1 rows --> extra template row
       $cls = ["dynamic-table-row"];
       if ($rowNumber == $rowCount)
         $cls[] = "new-table-row";
       if ($rowNumber == $rowCount)
         $thisSuffix = false;
       else
         $thisSuffix = $rowNumber;
       $newSuffix = $ctrl["suffix"];
       $newSuffix[] = $thisSuffix;
       $ctrl["_render"]->displayValue = false;
       $ctrl["_render"]->currentParentRow = $rowNumber;
       $addToSumValueBeforeRow = $ctrl["_render"]->addToSumValue;
       $rowTxt = [];

       $myRowIdFieldName = $rowIdFieldName;
       $myRowIdFieldNameOrig = $rowIdFieldNameOrig;
       foreach($newSuffix as $suffix) {
         $myRowIdFieldName .= "[{$suffix}]";
         $myRowIdFieldNameOrig .= "[]";
       }
       $myRowId = $rowIdCount;
       if (isset($ctrl["_values"])) {
         $myRowId = getFormValue($myRowIdFieldName, $layout["type"], $ctrl["_values"]["_inhalt"], $myRowId);
       }
       $lastRowId = $ctrl["_render"]->currentRowId;
       $ctrl["_render"]->currentRowId = getBaseName($ctrl["_render"]->currentParent)."{".$myRowId."}";
       $ctrl["_render"]->rowIdToNumber[ $ctrl["_render"]->currentRowId ] = $ctrl["_render"]->currentParent."[".$rowNumber."]";
       $ctrl["_render"]->rowNumberToId[ $ctrl["_render"]->currentParent."[".$rowNumber."]" ] = $ctrl["_render"]->currentRowId;
?>
       <tr class="<?php echo implode(" ", $cls); ?>">
<?php

        if (!$noForm) {
          echo "<input type=\"hidden\" value=\"".htmlspecialchars($myRowId)."\" name=\"".htmlspecialchars($myRowIdFieldName)."\" orig-name=\"".htmlspecialchars($myRowIdFieldNameOrig)."\" class=\"store-row-id\"/>";
          echo "<input type=\"hidden\" value=\"".htmlspecialchars($layout["type"])."\" name=\"".htmlspecialchars($rowIdFieldTypeName)."\"/>";
        }

        if ($withRowNumber)
          echo "<td class=\"row-number\">".($rowNumber+1)."</td>";

        if ($withExpand) {
          echo "<td class=\"expand-toggle\">";
          if ($noForm)
            echo "<i class=\"expand-toggle-expand fa fa-plus-square-o\" aria-hidden=\"true\"></i><i class=\"expand-toggle-compress fa fa-minus-square-o\" aria-hidden=\"true\"></i>";
          echo "</td>";
        }

        if (!$noForm) {
          echo "<td class=\"delete-row\">";
          echo "<a href=\"\" class=\"delete-row\"><i class=\"fa fa-fw fa-trash\"></i></a>";
          echo "</td>";
        }

        foreach ($layout["columns"] as $i => $col) {
          if (!isset($col["opts"]))
            $col["opts"] = [];
          if (!$noForm && in_array("hide-edit", $col["opts"]))
            continue;

          $tdClass = [ "{$ctrl["id"]}-col-$i" ];
          if (in_array("title", $col["opts"]))
            $tdClass[] = "dynamic-table-column-title";
          else
            $tdClass[] = "dynamic-table-column-no-title";
          $tdClass[] = "dynamic-table-cell";
          $tdClass[] = "dynamic-table-col-$i";
          if ($layout["columns"][$i]["_hideable_isHidden"])
            $tdClass[] = "hide-column-manual";

          if (in_array("sum-over-table-bottom", $col["opts"])) {
            $col["addToSum"][] = "col-sum-".$layout["id"]."-".$i;
            $hasPrintSumFooter |= true;
            if ($col["type"] == "group") {
              $colWidthSum = 0;
              foreach ($col["children"] as $j => $child) {
                if (isset($child["editWidth"]) && !$noForm)
                  $child["width"] = $child["editWidth"];
                if (isset($child["width"]) && $child["width"] == -1) continue;
                if (isset($child["width"])) {
                  $colWidthSum += $child["width"];
                } else {
                  $colWidthSum += 1;
                }
                if ($colWidthSum > 12) break;
                if (!isset($child["opts"]) || !in_array("sum-over-table-bottom", $child["opts"]))
                  continue;
                $sumOverTableBottomChild = "col-sum-".$layout["id"]."-".$i."-".$child["id"];
                $col["children"][$j]["addToSum"][] = $sumOverTableBottomChild;
              }
            }
          }
          if (!empty($col["printSumFooter"]))
            $hasPrintSumFooter |= true;

          $newCtrl = ["wrapper"=> "td", "suffix" => $newSuffix, "class" => $tdClass ];
          if ($noForm)
            $ctrl["_render"]->displayValue = false;

          ob_start();
          renderFormItem($col, array_merge($ctrl, $newCtrl));
          $colTxt = ob_get_contents();
          ob_end_clean();

          if (isset($col["editWidth"]) && !$noForm)
            $col["width"] = $col["editWidth"];
          if (isset($col["width"]) && $col["width"] == -1) {
            // skip output
          } else {
            echo $colTxt;
          }

          if (in_array("title", $col["opts"]))
            $rowTxt[] = $ctrl["_render"]->displayValue;
        }

        $refname = getFormName($ctrl["name"]);
        $ctrl["_render"]->templates["<{rowTxt:".$refname."[".$rowNumber."]}>"] = implode(", ", $rowTxt);

        $addToSumDifference = [];
        foreach($ctrl["_render"]->addToSumValue as $addToSumId => $sum) {
          if (isset($addToSumValueBeforeRow[$addToSumId]))
            $before = $addToSumValueBeforeRow[$addToSumId];
          else
            $before = 0.00;
          $addToSumDifference[$addToSumId] = $sum - $before;
        }
        $ctrl["_render"]->addToSumValueByRowRecursive[$refname."[".$rowNumber."]"] = $addToSumDifference;
        $ctrl["_render"]->currentRowId = $lastRowId;

?>
       </tr>
<?php
     }
?>
    </tbody>
<?php

    $addToSumDifference = [];
    foreach($ctrl["_render"]->addToSumValue as $addToSumId => $sum) {
      if (isset($addToSumValueBeforeTable[$addToSumId]))
        $before = $addToSumValueBeforeTable[$addToSumId];
      else
        $before = 0.00;
      $addToSumDifference[$addToSumId] = $sum - $before;
    }
    $ctrl["_render"]->addToSumValueByRowRecursive[$refname] = $addToSumDifference;

    if ($hasPrintSumFooter) {

?>
       </tr>
<?php
     }
?>
    </tbody>
<?php
    if ($hasPrintSumFooter) {
        $addToSumDifference = [];
?>
    <tfoot>
      <tr>
<?php
        $colSpan = 0;
        if (!$noForm)
          $colSpan++; # delete-row
        if ($withRowNumber)
          $colSpan++;
        if ($withExpand)
          $colSpan++;
        if ($colSpan > 0)
          echo "<th colspan=\"{$colSpan}\"></th>";

        foreach ($layout["columns"] as $i => $col) {
          if (!isset($col["opts"])) $col["opts"] = [];
          if (!$noForm && in_array("hide-edit", $col["opts"]))
            continue;
          $sumOverTableBottom = false;
          if (in_array("sum-over-table-bottom", $col["opts"])) {
            $sumOverTableBottom = "col-sum-".$layout["id"]."-".$i;
            if (!isset($col["printSumFooter"]))
              $col["printSumFooter"] = [];
            array_unshift($col["printSumFooter"], $sumOverTableBottom);
          }
          $cls = [ "dynamic-table-cell", "dynamic-table-col-$i" ];
          if ($layout["columns"][$i]["_hideable_isHidden"])
            $cls[] = "hide-column-manual";
          if (isset($col["printSumFooter"]) && count($col["printSumFooter"]) > 0)
            $cls[] = "cell-has-printSum";
          else
            $col["printSumFooter"] = [];
          $colTxt = "<th class=\"".implode(" ", $cls)."\">";
          foreach ($col["printSumFooter"] as $psIdF) {
            $children = [ [ $psIdF, $col, true] ];
            if ($psIdF == $sumOverTableBottom && $col["type"] == "group") {
              $children = [];
              $colWidthSum = 0;
              foreach ($col["children"] as $child) {
                if (isset($child["editWidth"]) && !$noForm)
                  $child["width"] = $child["editWidth"];
                if (isset($child["width"]) && $child["width"] == -1) continue;
                if (!$noForm && isset($child["opts"]) && in_array("hide-edit", $child["opts"]))
                  continue;
                if (isset($child["width"])) {
                  $colWidthSum += $child["width"];
                } else {
                  $colWidthSum += 1;
                }
                if ($colWidthSum > 12) break;
                if (!isset($child["opts"]) || !in_array("sum-over-table-bottom", $child["opts"])) {
                  $children[] = [ null, $child, false ];
                } else {
                  $sumOverTableBottomChild = "col-sum-".$layout["id"]."-".$i."-".$child["id"];
                  $children[] = [ $sumOverTableBottomChild, $child, false ];
                }
              }
            }

            foreach ($children as $childMeta) {
              $psId = $childMeta[0];
              $child = $childMeta[1];
              $clearWidth = $childMeta[2];

              if ($psId == null) {
                $childCls = [];
                if (isset($child["editWidth"]) && !$noForm)
                  $child["width"] = $child["editWidth"];
                if (isset($child["width"]))
                  $childCls[] = "col-xs-{$child["width"]}";
                $colTxt .= "<div class=\"".implode(" ", $childCls)."\">&nbsp;</div>";
                continue;
              }

              if (isset($ctrl["_render"]->addToSumMeta[$psId])) {
                $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
              } elseif ($child["type"] != "group") {
                $newMeta = $child;
              } else {
                $colTxt .= "missing meta data for $psId = $value";
                $newMeta = [ "id" => $child["id"], "type" => "money", "currency" => "€", "printSumDefer" => $psId ];
                #continue;
              }
              unset($newMeta["addToSum"]);
              if (isset($newMeta["width"]) && $clearWidth)
                unset($newMeta["width"]);
              if (isset($newMeta["editWidth"]) && $clearWidth)
                unset($newMeta["editWidth"]);

              if (isset($addToSumDifference[$psId]))
                $value = $addToSumDifference[$psId];
              else
                $value = 0.00;
              $value = number_format($value, 2, ".", "");
              $newMeta["value"] = $value;

              $newMeta["opts"][] = "is-sum";
              if (!$noForm && in_array("hide-edit", $newMeta["opts"]))
                continue;

              if (isset($newMeta["printSumDefer"])) {
                if (isset($newMeta["printSum"])) {
                  unset($newMeta["printSum"]);
                }
              } else {
                $newMeta["printSum"] = [ $psId ];
              }
              if (count($col["printSumFooter"]) > 1 && isset($newMeta["name"]) && !isset($newMeta["title"])) {
                $newMeta["title"] = $newMeta["name"];
              }
  
              $newCtrl = $ctrl;
              $newCtrl["suffix"][] = "print-foot";
              $newCtrl["suffix"][] = $layout["id"];
              $newCtrl["render"][] = "no-form";
              unset($newCtrl["_values"]);
              ob_start();
              renderFormItem($newMeta, $newCtrl);
              $colTxt .= ob_get_contents();
              ob_end_clean();
            }
          }
          $colTxt .= "</th>";
          if (isset($col["editWidth"]) && !$noForm)
            $col["width"] = $col["editWidth"];
          if (isset($col["width"]) && $col["width"] == -1) {
            // hide column
          } else {
            echo $colTxt;
          }
        }
?>
      </tr>
    </tfoot>
<?php
    } /* if has column sums */
?>
  </table>
<?php
  $ctrl["_render"]->displayValue = false;
  $ctrl["_render"]->currentParent = $myParent;
  $ctrl["_render"]->currentParentRow = $myParentRow;

}

function evalPrintSum($psId, $sums, &$src = []) {
  if (substr($psId, 0, 5) != "expr:") {
    $src[] = $psId;
    if (!isset($sums[$psId]))
      return "0";
    return $sums[$psId];
  }

  $psId = trim(substr($psId, 5));
  $psId = preg_replace_callback('/%([^\s]+)/', function($m) use($sums, &$src) {
    $src[] = $m[1];
    if (!isset($sums[$m[1]])) return "0";
    return $sums[$m[1]];
  }, $psId);
  $psId = preg_replace('/[^\d\.\s+-]/', '', $psId); # ensure only match is in here

  return eval("return ($psId);");
}

# FIXME: Wenn invref nicht in Tabelle verwendet wird, macht es nur dann sinn, wenn bestimmte otherForm Referenzen ausgewertet werden. D.h. im aktuellen Dokument: referenziert alles, im anderen Dokument: je nach Position von otherForm Element.

function renderFormItemInvRef($layout,$ctrl) {
  list ($noForm, $noFormMarkup, $noFormCompress) = isNoForm($layout, $ctrl);

  $refId = $ctrl["_render"]->currentRowId; # false if out of table

  $hasForms = isset($layout["otherForms"]);

  $currentFormId = false;
  if (isset($ctrl["_values"])) {
    $currentFormId = $ctrl["_values"]["id"];
  }

  if ($refId === false && $currentFormId === false) # nothing other forms could reference here
    return false;
  if ($refId === false && !$hasForms) # no other forms that could reference this given
    return false;

  if (isset($layout["printSum"]))
    $printSum = $layout["printSum"];
  else
    $printSum = [];

  if (isset($layout["printSumLayout"])) {
    foreach ($layout["printSumLayout"] as $psId => $newMeta) {
      if (!isset($newMeta["id"]))
        $newMeta["id"] = "printSum-{$layout["id"]}";
      $ctrl["_render"]->addToSumMeta[$psId] = $newMeta;
    }
  }

  $refMe = [];
  $refMeOrder = [];

  if ($hasForms && $currentFormId !== false) {
    $forms = [];
    // find other forms
    if (isset($ctrl["_render"]->otherForm[$layout["id"]])) {
      $forms = $ctrl["_render"]->otherForm[$layout["id"]];
    } else {
      foreach ($layout["otherForms"] as $formFilterDef) {
        $f = ["type" => $formFilterDef["type"]];
        if (isset($formFilterDef["state"]))
          $f["state"] = $formFilterDef["state"];
        $al = dbFetchAll("antrag", $f);
        foreach ($al as $a) {
          if (isset($formFilterDef["referenceFormField"])) {
            $r0 = dbGet("inhalt", ["antrag_id" => $a["id"], "fieldname" => $formFilterDef["referenceFormField"], "contenttype" => "otherForm", "value" => $currentFormId ]);
            $r1 = false;
            if ($refId === false) # we're not in a table so lookup otherForm in-Table references (this is unsupported if we're in table)
              $r1 = dbFetchAll("inhalt", ["antrag_id" => $a["id"], "fieldname" => [ "LIKE", $formFilterDef["referenceFormField"]."[%" ], "contenttype" => "otherForm", "value" => $currentFormId ]);
            if ($r0 === false && ($r1 === false || count($r1) == 0)) continue;
          }
          $forms[$a["id"]]["antrag"] = $a;
          if (!isset($formFilterDef["addToSum"]))
            $formFilterDef["addToSum"] = [];
          if (!isset($forms[$a["id"]]["_addToSum"]))
            $forms[$a["id"]]["_addToSum"] = [];
          foreach ($formFilterDef["addToSum"] as $src => $dstA) {
            if (!isset($forms[$a["id"]]["_addToSum"][$src]))
              $forms[$a["id"]]["_addToSum"][$src] = [];
            $forms[$a["id"]]["_addToSum"][$src] = array_merge($forms[$a["id"]]["_addToSum"][$src], $dstA);
          }
          if (!isset($forms[$a["id"]]["_referenceFormField"]))
            $forms[$a["id"]]["_referenceFormField"] = [];
          if (isset($formFilterDef["referenceFormField"]))
            $forms[$a["id"]]["_referenceFormField"][] = $formFilterDef["referenceFormField"];
        }
      }
      $ctrl["_render"]->otherForm[$layout["id"]] = $forms;
    }

    foreach (array_keys($forms) as $aId) {
      $ro = "";
      if (in_array("skip-referencesId", $layout["opts"]))
        $ro = "skip-referencesId";
      $t = renderOtherAntrag($aId, $ctrl, $ro);

      $otherCtrl = $t["ctrl"];
      $f = $t["form"];
      $a = $t["antrag"];

      if (!isset($otherCtrl["_render"])) {
        echo "cannot identify references due to nesting";
        continue;
      }
      $orderBy = [];
      if (isset($layout["orderBy"])) {
        foreach ($layout["orderBy"] as $o) {
          if ($o == "id") {
            $orderBy[] = $aId;
          } elseif (substr($o,0,6) == "field:") {
            $fieldName = substr($o,6);
            $fieldValue = getFormValueInt($fieldName, null, $a["_inhalt"], "");
            $orderBy[] = $fieldValue;
          } else
            die("unknown sort criteria $o");
        }
      }
      if (count($orderBy) == 0)
        $orderBy[] = $aId;

      if ($refId === false) {
        # we're not in a table
        if ($currentFormId === false || $currentFormId == "") die("empty form id");
        if ($currentFormId === false || $currentFormId == "") continue;
        if (!isset($otherCtrl["_render"]->referencedByOtherForm[(int) $currentFormId])) die("no reference to this form $currentFormid");
        if (!isset($otherCtrl["_render"]->referencedByOtherForm[(int) $currentFormId])) continue;
        $referenceFormFields = array_unique($forms[$aId]["_referenceFormField"]);
        $addToSum = $forms[$aId]["_addToSum"];
        foreach ($referenceFormFields as $referenceFormField) {
          if (!isset($otherCtrl["_render"]->referencedByOtherForm[(int) $currentFormId][$referenceFormField])) die("no reference in field $referenceFormField");
          if (!isset($otherCtrl["_render"]->referencedByOtherForm[(int) $currentFormId][$referenceFormField])) continue;
          foreach( $otherCtrl["_render"]->referencedByOtherForm[(int) $currentFormId][$referenceFormField] as $r) {
            $refMe[$aId][] = ["ctrl" => $otherCtrl, "ref" => $r, "form" => $f, "antrag" => $a, "_addToSum" => $addToSum ];
            $refMeOrder[$aId] = $orderBy;
          }
        }
      } else if (isset($otherCtrl["_render"]->referencedBy[$refId])) {
        # we're in a table. the other forms needs to reference
        $addToSum = $forms[$aId]["_addToSum"];
        foreach( $otherCtrl["_render"]->referencedBy[$refId] as $r) {
          $refMe[$aId][] = ["ctrl" => $otherCtrl, "ref" => $r, "form" => $f, "antrag" => $a, "_addToSum" => $addToSum ];
          $refMeOrder[$aId] = $orderBy;
        }
      }
    }
  }

  /* sort $refMe by $refMeOrder */
  uksort($refMe, function ($a, $b) use ($refMeOrder) {
    if (!isset($refMeOrder[$a]) || !isset($refMeOrder[$b]))
      return 0;
    $oA = $refMeOrder[$a];
    $oB = $refMeOrder[$b];
    if (count($oA) != count($oB))
      return 0;

    for($i = 0; $i < count($oA); $i++) {
      if ($oA[$i] < $oB[$i]) return -1;
      if ($oA[$i] > $oB[$i]) return 1;
    }

    return 0;
  });

  foreach ($refMe as $grp => $rr) {
    for ($i = count($rr) - 1; $i >= 0; $i--) {
      $r = $rr[$i];
      $refRow = $r["ref"];
      $refCtrl = $r["ctrl"];
      $addToSum = $r["_addToSum"];
      if ($refRow == "[]")
        $sums = $refCtrl["_render"]->addToSumValue;
      else
        $sums = $refCtrl["_render"]->addToSumValueByRowRecursive[$refRow];

      foreach (array_keys($addToSum) as $psId) {
        $src = [];
        $value = evalPrintSum($psId, $sums, $src);
        $value = number_format($value, 2, ".", "");
        foreach($addToSum[$psId] as $dstPsId) {
          if (!isset($ctrl["_render"]->addToSumValue[$dstPsId]))
            $ctrl["_render"]->addToSumValue[$dstPsId] = 0.00;
          $ctrl["_render"]->addToSumValue[$dstPsId] += (float) $value;
          foreach ($src as $srcPsId) {
            if (isset($refCtrl["_render"]->addToSumMeta[$srcPsId]) && !isset($ctrl["_render"]->addToSumMeta[$dstPsId])) {
              $ctrl["_render"]->addToSumMeta[$dstPsId] = $refCtrl["_render"]->addToSumMeta[$srcPsId];
              break;
            }
          }
        }
      }
    }
  }

  if ($layout["width"] == -1)
    return false;

  if ($hasForms && count($refMe) == 0)
    return false;

  $myExtraFooterOut = false;
  if (isset($layout["extraFooter"])) {
    $myExtraFooterOut = "";
    foreach($layout["extraFooter"] as $newMeta) {
      ob_start();
      renderFormItem($newMeta, $ctrl);
      $myExtraFooterOut .= ob_get_contents();
      ob_end_clean();
    }
  }

  $tPattern = newTemplatePattern($ctrl, htmlspecialchars("<{invref:".uniqid().":".$refId."}>"));
  echo $tPattern;
  $ctrl["_render"]->templates[$tPattern] = htmlspecialchars("{".$tPattern."}"); // fallback
  $ctrl["_render"]->postHooks[] = function($ctrl) use ($tPattern, $layout, $refId, $ctrl, $noForm, $refMe, $hasForms, $currentFormId, $printSum, $myExtraFooterOut) {
    global $URIBASE;

    $withHeadline = in_array("with-headline", $layout["opts"]);
    $withAggByForm = in_array("aggregate-by-otherForm", $layout["opts"]);
    $withAgg = in_array("aggregate", $layout["opts"]);

    if ($noForm && isset($ctrl["_render"]->referencedBy[$refId])) {
      foreach( $ctrl["_render"]->referencedBy[$refId] as $r) {
        $refMe[-1][] = ["ctrl" => $ctrl, "ref" => $r, "_addToSum" => [] ];
      }
    }

    $columnSum = [];
    $saldoSum = [];
    $myOutBody = "";

    foreach ($refMe as $grp => $rr) {
      $otherFormSum = [];
      for ($i = count($rr) - 1; $i >= 0; $i--) {
        $r = $rr[$i];
        $refRow = $r["ref"];
        $refCtrl = $r["ctrl"];

        if ($refRow == "[]")
          $sums = $refCtrl["_render"]->addToSumValue;
        else
          $sums = $refCtrl["_render"]->addToSumValueByRowRecursive[$refRow];

        foreach ($printSum as $psId) {
          $src = [];
          $value = evalPrintSum($psId, $sums, $src);
          $value = number_format($value, 2, ".", "");
          if (!isset($columnSum[ $psId ]))
            $columnSum[ $psId ] = 0.00;
          $columnSum[ $psId ] += (float) $value;
          if (!isset($otherFormSum[ $psId ]))
            $otherFormSum[ $psId ] = 0.00;
          $otherFormSum[ $psId ] += (float) $value;

          foreach ($src as $srcPsId) {
            if (isset($refCtrl["_render"]->addToSumMeta[$srcPsId]) && !isset($ctrl["_render"]->addToSumMeta[$psId])) {
              $ctrl["_render"]->addToSumMeta[$psId] = $refCtrl["_render"]->addToSumMeta[$srcPsId];
              break;
            }
          }
        }

        if ($withAggByForm && $i > 0) continue; # not last
        if ($withAgg) continue;

        $myOutBody .= "    <tr>\n";
        if ($hasForms) {
          $revConfig = getFormConfig($r["antrag"]["type"], $r["antrag"]["revision"]);
          $caption = getAntragDisplayTitle($r["antrag"], $revConfig);
          $caption = trim(implode(" ", $caption));
          $url = str_replace("//","/", $URIBASE."/".$r["antrag"]["token"]);
          $myOutBody .= "<td>[".$r["antrag"]["id"]."] <a href=\"".htmlspecialchars($url)."\">".$caption."</a></td>";
        }
        if (!$withAggByForm) {
          $refRowId = $ctrl["_render"]->rowNumberToId[$refRow];
          $txtTr = getTrText($refRowId, $refCtrl);
          $txtTr = newTemplatePattern($ctrl, processTemplates($txtTr, $refCtrl));
          $myOutBody .= "      <td class=\"invref-txtTr\">{$txtTr}</td>\n"; /* Spalte: Quelle */
        }
  
        foreach ($printSum as $psId) {
          if ($withAggByForm)
            $value = $otherFormSum[$psId];
          else
            $value = evalPrintSum($psId, $sums, $src);

          if (!isset($saldoSum[$psId]))
            $saldoSum[$psId] = 0.00;
          $saldoSum[$psId] += $value;

          $value = number_format($value, 2, ".", "");
          if (isset($refCtrl["_render"]->addToSumMeta[$psId])) {
            $newMeta = $refCtrl["_render"]->addToSumMeta[$psId];
          } elseif (isset($ctrl["_render"]->addToSumMeta[$psId])) {
            $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
          } else {
            $newMeta = false;
          }
          if ($newMeta !== false) {
            if (isset($layout["printSumSaldo"]) && in_array($psId, $layout["printSumSaldo"])) {
              $newMeta["value"] = $saldoSum[$psId];
              unset($newMeta["printSum"]);
              unset($newMeta["addToSum"]);
            } else {
              $newMeta["value"] = $value;
              $newMeta["printSum"] = [ $psId ];
              $newMeta["addToSum"] = [ "invref-".$layout["id"]."-".printSumId($psId) ];
            }
            if (isset($newMeta["editWidth"]))
              unset($newMeta["editWidth"]);
            if (isset($newMeta["width"]))
              unset($newMeta["width"]);
            if (isset($newMeta["title"]))
              unset($newMeta["title"]);
            if (isset($layout["printSumWidth"]))
              $newMeta["width"] = $layout["printSumWidth"];
  
            $newCtrl = array_merge($refCtrl, ["wrapper"=> "td", "class" => [ "cell-has-printSum" ] ]);
            $newCtrl["suffix"][] = "print";
            $newCtrl["suffix"][] = $layout["id"];
            $newCtrl["render"][] = "no-form";
            unset($newCtrl["_values"]);
            ob_start();
            renderFormItem($newMeta, $newCtrl);
            $myOutBody .= newTemplatePattern($ctrl, processTemplates(ob_get_contents(), $newCtrl));
            ob_end_clean();
          } else {
            $myOutBody .= "    <td class=\"cell-has-printSum\">";
            $myOutBody .= "      <div data-printSum=\"".htmlspecialchars(printSumId($psId))."\">".htmlspecialchars($value)."</div>";
            $myOutBody .= "    </td>\n";
          }
        }
        $myOutBody .= "    </tr>\n";
      }
    }
    if (!$noForm && !$withAgg) {
      $myOutBody .= "    <tr class=\"invref-template summing-skip\">\n";
      if ($hasForms && !$withAggByForm) {
        $myOutBody .= "      <td></td>\n"; /* Spalte: Quelleformular */
      }
      $myOutBody .= "      <td class=\"invref-rowTxt\"></td>\n"; /* Spalte: Quelle */

      foreach ($printSum as $psId) {
        if (isset($layout["printSumSaldo"]) && in_array($psId, $layout["printSumSaldo"])) {
          $myOutBody .= "    <td></td>";
        } elseif (isset($ctrl["_render"]->addToSumMeta[$psId])) {
          $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
          $newMeta["addToSum"] = [ "invref-".$layout["id"]."-".printSumId($psId) ];
          $newMeta["printSum"] = [ $psId ];
          if (isset($newMeta["editWidth"]))
            unset($newMeta["editWidth"]);
          if (isset($newMeta["width"]))
            unset($newMeta["width"]);
          if (isset($layout["printSumWidth"]))
            $newMeta["width"] = $layout["printSumWidth"];

          $newCtrl = array_merge($ctrl, ["wrapper"=> "td", "class" => [ "cell-has-printSum" ] ]);
          $newCtrl["suffix"][] = "print";
          $newCtrl["suffix"][] = $layout["id"];
          $newCtrl["render"][] = "no-form";
          unset($newCtrl["_values"]);
          ob_start();
          renderFormItem($newMeta, $newCtrl);
          $myOutBody .= ob_get_contents();
          ob_end_clean();
        } else {
          $myOutBody .= "    <td class=\"cell-has-printSum\">";
            $myOutBody .= "    <div data-printSum=\"".htmlspecialchars(printSumId($psId))."\">no meta data for ".htmlspecialchars($psId)."</div>";
          $myOutBody .= "    </td>\n";
        }
      }

      $myOutBody .= "    </tr>\n";
    }

    if (!$withAgg) {
      $myOutHead = "  <thead>\n";
      $myOutHead .= "    <tr>\n";
      if ($hasForms && !$withAggByForm) {
        $myOutHead .= "      <td></td>\n"; /* Spalte: Quelleformular */
      }
      $myOutHead .= "      <td></td>\n"; /* Spalte: Quelle */
      foreach ($printSum as $psId) {
        $thCls = [];
        if (isset($ctrl["_render"]->addToSumMeta[$psId])) {
          $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
          $title = $psId;
          if (isset($newMeta["name"])) $title = $newMeta["name"];
          if (isset($newMeta["name"])) $title = $newMeta["name"];
          if (isset($newMeta["title"])) $title = $newMeta["title"];
          if ($newMeta["type"] == "money")
            $thCls[] = "text-right";
        } else {
          $title = $psId;
        }
        $myOutHead .= "    <th class=\"".implode(" ", $thCls)."\">".htmlspecialchars($title)."</th>";
      }
  
      $myOutHead .= "    </tr>\n";
      $myOutHead .= "  </thead>\n";
  
      $myOut = "<table class=\"table table-striped invref summing-table\" id=\"".htmlspecialchars($ctrl["id"])."\" name=\"".htmlspecialchars($ctrl["name"])."\"  orig-name=\"".htmlspecialchars($ctrl["orig-name"])."\">\n";
      if ($withHeadline) {
        $myOut .= $myOutHead;
      }
      $myOut .= "  <tbody>\n";
      $myOut .= $myOutBody;
      $myOut .= "  </tbody>\n";
      $numCol = 0;
      $myOut .= "  <tfoot>\n";
      $myOut .= "    <tr>\n";
      if ($hasForms && !$withAggByForm) {
        $numCol++;
        $myOut .= "      <td></td>\n"; /* Spalte: Quelleformular */
      }
      $numCol++;
      $myOut .= "      <td></td>\n"; /* Spalte: Quelle */
      foreach ($printSum as $psId) {
        $numCol++;
        if (isset($layout["printSumSaldo"]) && in_array($psId, $layout["printSumSaldo"])) {
          $myOut .= "    <td></td>";
        } elseif (isset($ctrl["_render"]->addToSumMeta[$psId])) {
          $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
          unset($newMeta["addToSum"]);
          $newMeta["printSum"] = [ "invref-".$layout["id"]."-".printSumId($psId) ];
          if (!isset($columnSum[ $psId ]))
            $columnSum[ $psId ] = 0.00;
          $newMeta["value"] = number_format($columnSum[ $psId ], 2, ".", "");
          $newMeta["opts"][] = "is-sum";
          if (isset($newMeta["editWidth"]))
            unset($newMeta["editWidth"]);
          if (isset($newMeta["width"]))
            unset($newMeta["width"]);
          if (isset($newMeta["title"]))
            unset($newMeta["title"]);
          if (isset($layout["printSumWidth"]))
            $newMeta["width"] = $layout["printSumWidth"];
  
          $newCtrl = array_merge($ctrl, ["wrapper"=> "th", "class" => [ "cell-has-printSum" ] ]);
          $newCtrl["suffix"][] = "print-foot";
          $newCtrl["suffix"][] = $layout["id"];
          $newCtrl["render"][] = "no-form";
          unset($newCtrl["_values"]);
          ob_start();
          renderFormItem($newMeta, $newCtrl);
          $myOut .= ob_get_contents();
          ob_end_clean();
        } else {
          $myOut .= "    <td class=\"cell-has-printSum\">";
            $myOut .= "    <div data-printSum=\"".htmlspecialchars(printSumId($psId))."\">no meta data for ".htmlspecialchars($psId)."</div>";
          $myOut .= "    </td>\n"; /* Spalte: Quelle */
        }
      }
  
      $myOut .= "    </tr>\n";
      if (isset($layout["extraFooter"])) {
        $myOut .= "    <tr><td colspan=\"$numCol\">\n";
        $myOut .= $myExtraFooterOut;
        $myOut .= "    </td></tr>\n";
      }
      $myOut .= "  </tfoot>\n";
      $myOut .= "</table>\n";
  
      if ($myOutBody == "") $myOut = "";
    } else { // !$withAgg
      $myOut = "<div>";
      foreach ($printSum as $psId) {
        if (isset($ctrl["_render"]->addToSumMeta[$psId])) {
          $newMeta = $ctrl["_render"]->addToSumMeta[$psId];
          unset($newMeta["addToSum"]);
          $newMeta["printSum"] = [ "invref-".$layout["id"]."-".printSumId($psId) ];
          if (!isset($columnSum[ $psId ]))
            $columnSum[ $psId ] = 0.00;
          $newMeta["value"] = number_format($columnSum[ $psId ], 2, ".", "");
          $newMeta["opts"][] = "is-sum";
          if (isset($newMeta["editWidth"]))
            unset($newMeta["editWidth"]);
          if (isset($newMeta["width"]))
            unset($newMeta["width"]);
          if (isset($layout["printSumWidth"]))
            $newMeta["width"] = $layout["printSumWidth"];
          if (count($printSum) > 1 && isset($newMeta["name"]) && !isset($newMeta["title"]))
            $newMeta["title"] = $newMeta["name"];
  
          $newCtrl = array_merge($ctrl, ["class" => [ "cell-has-printSum" ] ]);
          $newCtrl["suffix"][] = "print-foot";
          $newCtrl["suffix"][] = $layout["id"];
          $newCtrl["render"][] = "no-form";
          unset($newCtrl["_values"]);
          ob_start();
          renderFormItem($newMeta, $newCtrl);
          $myOut .= ob_get_contents();
          ob_end_clean();
        } else {
          $myOut .= "    <div data-printSum=\"".htmlspecialchars(printSumId($psId))."\">no meta data for ".htmlspecialchars($psId)."</div>";
        }
        $myOut .= "</div>";
      }
  
    }

    $ctrl["_render"]->templates[$tPattern] = processTemplates($myOut, $ctrl); // rowTxt is from displayValue and thus already escaped
  };
}
