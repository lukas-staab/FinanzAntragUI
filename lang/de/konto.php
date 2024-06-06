<?php

return [
    "manual-headline" => "Manueller Konto Import",
    "manual-headline-sub" => "Hier können die Banktransaktionen für ein Konto manuell hochgeladen werden. Dafür musst du dich bei deiner Bank anmelden und den Kontoauszug als .csv Datei herunterladen. Eine .csv Datei ist eine Art einfaches Tabellenblatt und kann hier direkt hochgeladen werden.",
    "csv-label-choose-konto" => "Wähle hier das Konto aus, für welches du den Kontoauszug hochladen willst.",
    "csv-upload-headline" => "CSV-Upload",
    "csv-upload-headline-sub" => "Lade hier die .csv Datei durch Auswählen oder Hineinziehen hoch. Die Inhalte werden noch nicht gespeichert, sondern zunächst nur als Vorschau angezeigt. Es kann zu Fehlern kommen, wenn die Datei nicht im richtigen Format gespeichert wurde.",
    "csv-draganddrop-fat-text" => "Füge hier die .csv Datei hinzu!",
    "csv-draganddrop-light-text" => "",
    "csv-draganddrop-sub-text" => "Ziehe die Datei hier in das Feld oder wähle sie über den Knopf aus. Es kann einen Moment dauern, bis die Informationen geladen werden.",
    "manual-button-reverse-csv-order" => "Reihenfolge der Einträge umkehren",
    "manual-button-reverse-csv-order-sub" => "Einige Banken exportieren Transaktionsdaten chronologisch aufsteigend, andere absteigend. StuFiS hätte gern den ältesten Eintrag zu erst. Falls die Tabelle falsch herum sortiert ist oder z.B. die Saldo-Validierung fehlschlägt, kannst du diese Option anwenden.",
    "manual-button-submit" => "Vorschau anzeigen",
    "manual-button-assign" => "Importieren",
    "csv-button-new-konto" => "Neues Konto anlegen",
    "csv-preview-first" => "Erster Eintrag (csv)",
    "csv-preview-last" => "Letzter Eintrag (csv)",
    "csv-no-transaction" => "Es gibt auf diesem Konto bisher keine Transaktionen",
    "csv-latest-saldo" => "aktueller Saldo",
    "csv-latest-date" => "Datum der letzten Kontobuchung",
    "csv-latest-zweck" => "letzter Verwendungszweck",
    "csv-import-success-msg" => ":transaction-amount Kontoauszüge erfolgreich importiert. Neuer Saldo ist: :new-saldo €",
    "transaction.headline" => "Zuordnung der Tabellenspalten",
    "transaction.headline-sub" => "Ordne hier den vorgegebenen Feldern die korrekte Tabellenüberschrift aus deiner .csv Datei hinzu. Du hast immer einen Beispielwert stehen, dan dem du dich orientieren kannst. Wenn du eine Zuordnung getroffen hast, wird dir darunter der erste und letzte Zeileneintrag der .csv Datei in der jeweiligen Spalte angezeigt. Bitte überprüfe die Zuordnung genau. Nach dem Drücken des Importieren-Knopfes werden die Daten so gespeichert und können nicht mehr angepasst werden. Das StuFiS wird sich in Zukunft die Zuordnung merken und dir diese direkt vorschlagen. Einzelne Felder wie Primanota müssen nicht zwingend gefüllt werden.",
    "label.transaction.date" => "Ausführungsdatum",
    "hint.transaction.date" => "z. B. 13.08.2021",
    "label.transaction.valuta" => "Valuta-/Wertstellungsdatum",
    "hint.transaction.valuta" => "z. B. 14.08.2021",
    "label.transaction.type" => "Transaktionstyp",
    "hint.transaction.type" => "z. B. Dauerauftrag, Bareinzahlung, ...",
    "label.transaction.empf_iban" => "Zahlungsbeteiligte:r IBAN",
    "hint.transaction.empf_iban" => "z. B. DE02120300000000202051",
    "label.transaction.empf_bic" => "Zahlungsbeteiligte:r BIC",
    "hint.transaction.empf_bic" => "z. B. BYLADEM1001",
    "label.transaction.empf_name" => "Zahlungsbeteiligte:r Name",
    "hint.transaction.empf_name" => "z. B. Maxi Musterstudi",
    "label.transaction.primanota" => "Primanota",
    "hint.transaction.primanota" => "z. B. 420",
    "label.transaction.value" => "Wert",
    "hint.transaction.value" => "z. B. 42,69",
    "label.transaction.saldo" => "Saldo",
    "hint.transaction.saldo" => "z. B. 42.690,00",
    "label.transaction.zweck" => "Verwendungszweck",
    "hint.transaction.zweck" => "z. B. Gute Lehre Abo",
    "label.transaction.comment" => "Kommentar",
    "hint.transaction.comment" => " ",
    "label.transaction.customer_ref" => "Kundenreferenz",
    "hint.transaction.customer_ref" => "ggfs. weitere Angaben zum Auftrag",
    'csv-verify-iban-error' => 'Validierungs-Fehler: Enthält ungültige IBANs',
    'csv-verify-money-error' => 'Validierungs-Fehler: Enthält nicht-numerische Daten',
    'csv-verify-balance-error-wrong-datatype' => 'Validierungs-Fehler: Falscher Datentyp',
    'csv-verify-balance-error' => 'Validierungsfehler: Es besteht evtl. keine lückenlose Transaktionshistorie.'

];
