(function (jQuery, Drupal, drupalSettings) {
  'use strict';

  document.onreadystatechange = function () {
    if (!window.H5P) {
      return;
    }
    if ('interactive' === document.readyState) {
      try {
        if (H5P.externalDispatcher) {
          H5P.externalDispatcher.on('xAPI', handleXAPIEvent);
        }
      }
      catch (error) {
        console.warn(error);
      }
    }
  }

  var handleXAPIEvent = function (event) {
    if (drupalSettings.h5p.drupal_h5p.H5P.debugIsEnabled) {
      console.log(event.data.statement);
    }

    var id = event.data.statement.object.definition.extensions[drupalSettings.h5p.drupal_h5p.H5P.contentIdKey];

    if (true === drupalSettings.h5p.drupal_h5p.H5P.captureAll ||
      drupalSettings.h5p.drupal_h5p.H5P.captureAllowed.includes(id)) {
      sendH5pXapiData(event.data.statement);
    }
  };

  var sendH5pXapiData = function (statement) {
    jQuery.ajax({
      url: Drupal.url('h5p_xapi/xapi/data/process'),
      type: 'POST',
      data: {
        statement: statement
      },
      dataType: 'json'
    });
  };

})(jQuery, Drupal, drupalSettings);
