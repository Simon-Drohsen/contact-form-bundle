pimcore.registerNS("pimcore.plugin.ContactFormBundle");

pimcore.plugin.ContactFormBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("ContactFormBundle ready!");
    }
});

var ContactFormBundlePlugin = new pimcore.plugin.ContactFormBundle();
