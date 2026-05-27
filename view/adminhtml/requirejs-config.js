var config = {
    paths: {
        'bynderjs': 'DamConsultants_Ahfproducts/js/bynder',
        'select2': 'DamConsultants_Ahfproducts/js/select2'
    },
    shim: {
        'bynderjs': {
            deps: ['jquery']
        },
        'select2': {
            deps: ['jquery']
        },
    },
    map: {
        '*': {
            'Magento_PageBuilder/template/form/element/html-code.html': 'DamConsultants_Ahfproducts/template/form/element/html-code.html',
            'Magento_PageBuilder/js/form/element/html-code': 'DamConsultants_Ahfproducts/js/form/element/html-code',
            'Magento_PageBuilder/template/content-type/video/default/master.html': 'DamConsultants_Ahfproducts/template/content-type/video/default/master.html',
            'Magento_PageBuilder/template/content-type/video/default/preview.html': 'DamConsultants_Ahfproducts/template/content-type/video/default/preview.html',
        },
    }
};