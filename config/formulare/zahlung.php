<?php

$config = [
  "title" => "Zahlung",
  "shortTitle" => "Zahlung",
  "state" => [ "payed" => [ "Gezahlt", ],
               "booked" => [ "Gezahlt und gebucht", ],
               "canceled" => [ "Storniert", ],
             ],
  "proposeNewState" => [
    "payed" => [ "booked" ],
  ],
  "createState" => "payed",
  "categories" => [
    "need-booking" => [
       [ "state" => "payed", "group" => "ref-finanzen" ],
    ],
  ],
  "validate" => [
    "postEdit" => [
      [ "state" => "payed", "doValidate" => "checkBeleg", ],
      [ "state" => "payed", "doValidate" => "checkKontenplan", ],
      [ "state" => "booked", "doValidate" => "checkBeleg", ],
      [ "state" => "booked", "doValidate" => "checkKontenplan", ],
      [ "state" => "booked", "doValidate" => "checkSum", ],
    ],
    "checkBeleg" => [
      [ "id" => "zahlung.grund.beleg",
        "otherForm" => [
          [ "type" => "auslagenerstattung", "state" => "ok", "validate" => "postEdit",
            "fieldMatch" => [
              [ "otherFormFieldName" => "genehmigung.jahr", "thisFormFieldName" => "zahlung.datum", "condition" => "matchYear", ],
            ],
          ],
          [ "type" => "auslagenerstattung", "state" => "instructed", "validate" => "postEdit",
            "fieldMatch" => [
              [ "otherFormFieldName" => "genehmigung.jahr", "thisFormFieldName" => "zahlung.datum", "condition" => "matchYear", ],
            ],
          ],
          [ "type" => "auslagenerstattung", "state" => "payed", "validate" => "postEdit",
            "fieldMatch" => [
              [ "otherFormFieldName" => "genehmigung.jahr", "thisFormFieldName" => "zahlung.datum", "condition" => "matchYear", ],
            ],
          ],
        ],
      ],
    ],
    "checkKontenplan" => [
     [ "id" => "kontenplan.otherForm",
       "otherForm" => [
         [ "type" => "kontenplan", "revisionIsYearFromField" => "zahlung.datum", "state" => "final" ],
       ],
     ],
    ],
  ],
  "permission" => [
    "canRead" => [
      [ "group" => "konsul" ],
    ],
    "canEditPartiell" => [
      [ "group" => "ref-finanzen", ],
    ],
    "canEditPartiell.field.genehmigung.recht.int.sturabeschluss" => [
      [ "state" => "payed", "group" => "ref-finanzen", ],
    ],
    "canEdit" => [
      [ "state" => "draft", "group" => "ref-finanzen", ],
    ],
    "canBeCloned" => false,
    "canCreate" => [
      [ "hasPermission" => [ "group" => "ref-finanzen", "isCreateable" ] ],
    ],
    # Genehmigung durch StuRa
    "canStateChange.from.payed.to.booked" => [
      [ "group" => "ref-finanzen" ],
    ],
  ],
];

registerFormClass( "zahlung", $config );

