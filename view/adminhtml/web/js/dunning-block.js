define([
    'jquery',
    'Magento_Ui/js/modal/prompt',
    'mage/dataPost',
    'mage/validation'
], function ($, prompt, dataPost) {
    'use strict';

    /**
     * Asks for the reason before setting or removing a dunning block, then posts it.
     * The reason is mandatory when setting the block and optional when removing it.
     */
    return function (config, element) {
        var submitted = false;

        $(element).on('click', function (event) {
            event.preventDefault();

            if (submitted) {
                return;
            }

            prompt({
                title: config.title,
                content: config.content,
                validation: config.commentRequired,
                validationRules: config.commentRequired ? ['required-entry'] : [],
                actions: {
                    confirm: function (comment) {
                        if (submitted) {
                            return;
                        }
                        submitted = true;

                        dataPost().postData({
                            action: config.url,
                            data: {
                                'set_blocked': config.setBlocked,
                                comment: String(comment).trim()
                            }
                        });
                    }
                }
            });
        });
    };
});
