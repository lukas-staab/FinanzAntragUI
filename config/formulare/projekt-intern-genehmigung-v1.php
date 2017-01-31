<?php

$config = [
  "captionField" => [ "projekt.name", "projekt.zeitraum" ],
  "revisionTitle" => "Version 20170126",
  "permission" => [
    "isCorrectGremium" => [
      [ "field:projekt.org.name" => "isIn:data-source:own-orgs" ],
    ],
    "isCreateable" => true,
  ],
  "mailTo" => [ "mailto:ref-finanzen@tu-ilmenau.de", "field:projekt.org.mail", "field:projekt.leitung" ],
  "referenceField" => [ "name" => "genehmigung.antrag", "type" => "otherForm" ],
];

$layout = [
 [
   "type" => "h2", /* renderer */
   "id" => "head1",
   "autoValue" => "class:title",
 ],

 [
   "type" => "group", /* renderer */
   "width" => 12,
   "opts" => ["well"],
   "id" => "group0",
   "title" => "Genehmigung",
   "children" => [
     [ "id" => "genehmigung.recht.grp",   "title" =>"Rechtsgrundlage",        "type" => "group",    "width" => 12, "children" => [

       [ "id" => "genehmigung.recht", "text" => "Büromaterial: StuRa-Beschluss 21/20-07: bis zu 50 EUR", "type" => "radio", "value" => "buero", "width" => 12, "opts" => ["required"], ],
       [ "id" => "genehmigung.recht", "text" => "Fahrtkosten: StuRa-Beschluss 21/20-08: Fahrtkosten", "type" => "radio", "value" => "fahrt", "width" => 12, "opts" => ["required"], ],
       [ "id" => "genehmigung.recht", "text" => "Verbrauchsmaterial: Finanzordnung §11: bis zu 150 EUR", "type" => "radio", "value" => "verbrauch", "width" => 12, "opts" => ["required"], ],

       [ "id" => "genehmigung.recht", "text" => "Beschluss StuRa-Sitzung\nFür FSR-Titel ist außerdem ein FSR Beschluss notwendig.", "type" => "radio", "value" => "stura", "width" => 6, "opts" => ["required"], ],
       [ "id" => "genehmigung.recht.stura.beschluss", "title" => "Beschluss-Nr", "type" => "text", "width" => 2, ],
       [ "id" => "genehmigung.recht.stura.datum", "title" => "vom", "type" => "date", "width" => 2, ],

       [ "id" => "genehmigung.recht", "text" => "Beschluss Fachschaftsrat/Referat\nStuRa-Beschluss 21/21-05: für ein internes Projekt bis zu 250 EUR", "type" => "radio", "value" => "fsr", "width" => 6, "opts" => ["required"], ],
       [ "id" => "genehmigung.recht.int.gremium", "title" => "Gremium", "type" => "text", "width" => 2, "onClickFillFrom" => "projekt.org.name"],
       [ "id" => "genehmigung.recht.int.datum", "title" => "vom", "type" => "date", "width" => 2,  "onClickFillFrom" => "projekt.protokoll", "onClickFillFromPattern" => '\d\d\d\d-\d\d-\d\d'],
     ], ],
     [ "id" => "genehmigung.titel",   "title" =>"Titel im Haushaltsplan", "type" => "text",     "width" => 6, "opts" => ["required", "hasFeedback"], "minLength" => "5" ],
     [ "id" => "genehmigung.konto",   "title" =>"Konto (Gnu-Cash)",       "type" => "text",     "width" => 6, "opts" => [ "hasFeedback"], "minLength" => "5", "placeholder" => "Wie Titel" ],
     [ "id" => "genehmigung.antrag",  "title" =>"Antrag war",             "type" => "otherForm",     "width" => 12, "opts" => ["required", "hasFeedback"] ],
     [ "id" => "genehmigung.hinweis", "title" =>"Auflagen",               "type" => "textarea", "width" => 12, "opts" => [ "hasFeedback"] ],
   ],
 ],

 [
   "type" => "group", /* renderer */
   "width" => 12,
   "opts" => ["well"],
   "id" => "group1",
   "title" => "Genehmigtes Projekt",
   "children" => [
     [ "id" => "projekt.name",        "title" =>"Projektname",                        "type" => "text",   "width" => 12, "opts" => ["required", "hasFeedback"], "minLength" => "10" ],
     [ "id" => "projekt.leitung",     "title" =>"Projektverantwortlich (eMail)",      "type" => "email",  "width" => 12, "placeholder" => "Vorname.Nachname@tu-ilmenau.de", "prefill" => "user:mail", "opts" => ["required", "hasFeedback"] ],
#     [ "id" => "projekt.org.name2",    "title" =>"Projekt von",                        "type" => "select", "width" =>  12, "data-source" => "own-orgs", "placeholder" => "Institution wählen", "opts" => ["required", "hasFeedback"] ],
     [ "id" => "projekt.org.name",    "title" =>"Projekt von",                        "type" => "text", "width" =>  6, "data-source" => "own-orgs", "placeholder" => "Institution wählen", "opts" => ["required", "hasFeedback"] ],
     [ "id" => "projekt.org.mail",    "title" =>"Benachrichtigung (Mailingliste zu \"Projekt von\")",  "type" => "email",  "width" =>  6, "data-source" => "own-mailinglists", "placeholder" => "Mailingliste wählen", "opts" => ["required", "hasFeedback"] ],
     [ "id" => "projekt.protokoll",   "title" =>"Projektbeschluss (Wiki Direktlink)", "type" => "url",    "width" => 12, "placeholder" => "https://wiki.stura.tu-ilmenau.de/protokoll/...", "opts" => ["required","hasFeedback","wikiUrl"], "pattern" => "^https:\/\/wiki\.stura\.tu-ilmenau\.de\/protokoll\/.*", "pattern-error" => "Muss mit \"https://wiki.stura.tu-ilmenau.de/protokoll/\" beginnen." ],
     [ "id" => "projekt.zeitraum",    "title" =>"Projektdauer",                       "type" => "daterange", "width" => 12,  "opts" => [ "required"] ],
   ],
 ],

 [
   "type" => "table", /* renderer */
   "id" => "finanzgruppentbl",
   "opts" => ["with-row-number","with-headline"],
   "width" => 12,
   "rowCountField" => "numgrp",
   "columns" => [
     [ "id" => "geld.name",        "name" => "Ein/Ausgabengruppe",                 "type" => "text",   "width" => 4, "opts" => [ "required" ] ],
     [ "id" => "geld.einnahmen",   "name" => "Einnahmen",                          "type" => "money",  "width" => 2, "currency" => "€", "opts" => ["sum-over-table-bottom"] ],
     [ "id" => "geld.ausgaben",    "name" => "Ausgaben",                           "type" => "money",  "width" => 2, "currency" => "€", "opts" => ["sum-over-table-bottom"] ],
     [ "id" => "geld.titel",       "name" => "Titel",                              "type" => "text",   "width" => 2, "placeholder" => "s. Genehmigung", ],
     [ "id" => "geld.konto",       "name" => "Konto (Gnu-Cash)",                   "type" => "text",   "width" => 2, "placeholder" => "s. Genehmigung", ],
   ], // finanzgruppentbl
 ],

 [
   "type" => "textarea", /* renderer */
   "id" => "projekt.beschreibung",
   "title" => "Projektbeschreibung",
   "width" => 12,
   "min-rows" => 10,
   "opts" => ["required"]
 ],

 [
   "type" => "plaintext", /* renderer */
   "title" => "Erläuterung",
   "id" => "info",
   "width" => 12,
   "opts" => ["well"],
   "value" => "Der Projektantrag muss rechtzeitig vor Projektbeginn eingereicht werden. Das Projekt darf erst durchgeführt werden, wenn der Antrag genehmigt wurde.",
 ],

];

/* formname , formrevision */
registerForm( "projekt-intern-genehmigung", "v1", $layout, $config );
