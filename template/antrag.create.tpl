<?php

global $formconfig;
# vim: set syntax=php:
?>

<form id="newantrag" role="form" action="index.php" method="POST">
  <input type="hidden" name="action" value="antrag.create"/>
  <input type="hidden" name="nonce" value="<?php echo $nonce; ?>"/>

<?php

  renderForm($formconfig);

?>

  <button type="submit" class='btn btn-success pull-right' name="submit" id="submit">Absenden</button>

</form>


<!--
      <form id="form_antrag" role= "form" action="sendNewAntrag.php" method="POST" data-toggle="validator">

        <label for="header-data"> Meta-Daten </label>
        <div class="well" id="header-data">

          <div class="form-group has-feedback">
            <label for="projekt-titel">Projekttitel:</label>
            <input type="text" class="form-control projekt" name="projekt-titel" minlength="10" maxlength="150" required>
            <span class="glyphicon form-control-feedback" aria-hidden="true"></span>
            <div class="help-block with-errors"></div>
          </div>


          <div class="row">
            <div class="col-xs-5 from-group">
              <label for="von-pick" class="control-label">Projekt von:</label>
              <select class="selectpicker form-control" data-live-search="true" title="Institution wählen" name="von-pick" multiple>
                <optgroup label="Fachschaftsräte">
                  <option>FSR EI</option>
                  <option>FSR IA</option>
                  <option>FSR MB</option>
                  <option>FSR MN</option>
                  <option>FSR WM</option>
                </optgroup>
                <optgroup label="Referate">
                  <option>Ref Soz</option>
                  <option>Ref Int</option>
                  <option title="Ref IT" data-icon="glyphicon-heart">Ref IT</option>
                </optgroup>
                <optgroup label="AGs">
                  <option>AG Interclub</option>
                  <option>AG Umbau</option>
                </optgroup>
              </select>
              <div class="help-block with-errors"></div>
            </div>
            <div class="col-xs-7 form-group">
              <label for="projekt-verantwortlich" class="control-label">Projektverantwortlich (Mail):</label>
              <input type="email" class="form-control" id="projekt-verantwortlich" data-error="Keine gültige E-Mail Adresse!" required name="projekt-verantwortlich" placeholder="Vorname.Nachname@tu-ilmenau.de">
              <div class="help-block with-errors"></div>
            </div>
          </div>
          <div class="form-group">
            <label for="projekt-beschluss" class="control-label">Projektbeschluss (wiki Direkt-Link mit http):</label>
            <input type="url" class="form-control" name="projekt-beschluss" id="projekt-beschluss" placeholder="https://www.wiki.stura.tu-ilmenau.de/..." required>
            <div class="help-block with-errors"></div>
          </div>
          <div class="row form-group">
            <div class="col-xs-4">
              <label for="projekt-titel" class="control-label">Projektbeginn:</label>
              <input type="text" class="form-control datepicker" name="date-von"  id="date-von" data-date-format="yyyy-mm-dd">
            </div>
            <div class="col-xs-4">
              <label for="projekt-titel" class="control-label">Projektende:</label>
              <input type="text" class="form-control datepicker" name="date-bis" id="date-bis" data-date-format="yyyy-mm-dd">
            </div>
          </div>
        </div>


        <table class="table" id="table-aufstellung">
          <thead>
            <tr class="dontCount">
              <th>Nr.</th>
              <th>Aus/Eingabengruppe</th>
              <th>Einnahmen</th>
              <th>Ausgaben</th>
            </tr>
          </thead>
          <tbody>

          </tbody>
          <tfoot>
            <tr class="dontCount">
              <th colspan="2">
                <button type="button" class='addRowBtn btn btn-primary'>Neue Zeile</button>
                <button type="button" class='delRowBtn btn btn-warning'>Lösche Letzte Zeile</button>
              </th>
              <th class="in">You should not see this o.O</th>
              <th class="out">You should not see this o.O</th>
            </tr>
          </tfoot>
        </table>
        <div class="form-group">
          <label for="comment" class="control-label">Projektbeschreibung:</label>
          <textarea class="form-control" rows="7" name="comment" minlength="100" maxlength="750" required></textarea>
          <div class="help-block with-errors"></div>
        </div>

        <button type="submit" class='btn btn-success pull-right' name="submit" id="submit">Absenden</button>
      </form>



    <script>
      var nowTemp = new Date();
      var now = new Date(nowTemp.getFullYear(), nowTemp.getMonth(), nowTemp.getDate(), 0, 0, 0, 0);

      var pick_von = $('#date-von').datepicker({
        onRender: function(date) {
          return date.valueOf() < now.valueOf() ? 'disabled' : '';
        }
      }).on('changeDate', function(ev) {
        if (ev.date.valueOf() > pick_bis.date.valueOf()) {
          var newDate = new Date(ev.date)
          newDate.setDate(newDate.getDate());
          pick_bis.setValue(newDate);
        }
        pick_von.hide();
        $('#date-bis')[0].focus();
      }).data('datepicker');
      var pick_bis = $('#date-bis').datepicker({
        onRender: function(date) {
          return date.valueOf() < pick_von.date.valueOf() ? 'disabled' : '';
        }
      }).on('changeDate', function(ev) {
        pick_bis.hide();
      }).data('datepicker');
    </script>

    <script>
    //- $(...).validator('update') für dynmaische Validierung (neue Felder)
      var i=1;
      var addRow  = function(){
        $("#table-aufstellung").find('tbody')
          .append($('<tr>')
              .attr('id','zeile-'+i)
              .append($('<td>').attr('class', 'col-md-1').append(i))
              .append($('<td>')
                  .attr('class', 'col-md-6')
                  .append($('<input>')
                      .attr('type', 'text')
                      .attr('class', 'form-control')
                      .attr('name', 'titel-' + i)
                       )
                   )
              .append($('<td>')
                  .attr('class', 'col-md-1')
                  .append($('<div>')
                      .attr('class', 'input-group')
                      .append($('<input>')
                          .attr('type', 'text')
                          .attr('class', 'form-control in')
                          .attr('value', '0.00')
                          .attr('name', 'in-' + i)
                          .focusout(function(){
                            $(this).val(parseFloat($(this).val()).toFixed(2));
                            sumFunction();
                           })
                       )
                      .append($('<span>').attr('class', 'input-group-addon').text('€')))

              )
              .append($('<td>')
                  .attr('class', 'col-md-1')
                  .append($('<div>')
                      .attr('class', 'input-group bfh-number')
                      .append($('<input>')
                          .attr('type', 'text')
                        .attr('class', 'form-control out')
                          .attr('value', '0.00')
                          .attr('name', 'out-' + i)
                          .focusout(function(){
          $(this).val(parseFloat($(this).val()).toFixed(2));
          sumFunction();
        })
                           )
                      .append($('<span>').attr('class', 'input-group-addon').text('€'))
                       )

                   )
               );
        i++;
      }
      addRow();

      var removeRow = function(){
        $("#table-aufstellung").find('tbody').find('#zeile-'+(i-1)).remove();
        i = i==1 ? 1:i-1;
        sumFunction();
      }

      $(".addRowBtn").on('click',addRow);
      $(".delRowBtn").on('click',removeRow);

      var sumFunction = function(){
        var $dataRows = $("#table-aufstellung tr:not('.dontCount')");
        var sumIn = 0;
        var sumOut = 0;
        $dataRows.each(function() {
          var inEuro = $(this).find('.in').val();
          var outEuro = $(this).find('.out').val();
          sumIn  += parseFloat(inEuro);
          sumOut += parseFloat(outEuro);
        });
        $("#table-aufstellung").find('tfoot').find('.in').text("Σ " + sumIn.toFixed(2) + " €");
        $("#table-aufstellung").find('tfoot').find('.out').text("Σ " + sumOut.toFixed(2) + " €");
      };

      sumFunction(); //erstes Mal durchrechnen

    </script>

-->
