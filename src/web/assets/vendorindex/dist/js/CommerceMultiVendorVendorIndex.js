/** global: Craft */
/** global: Garnish */
/**
 * Vendor index class
 */
Craft.CommerceMultiVendor.VendorIndex = Craft.BaseElementIndex.extend(
    {
        editableVendorTypes: null,
        $newVendorBtnVendorType: null,
        $newVendorBtn: null,

        init: function(elementType, $container, settings) {
            this.on('selectSource', $.proxy(this, 'updateButton'));
            this.on('selectSite', $.proxy(this, 'updateButton'));
            this.base(elementType, $container, settings);
        },

        afterInit: function() {
            // Find which of the visible vendorTypes the user has permission to create new vendors in
            this.editableVendorTypes = [];

            for (var i = 0; i < Craft.CommerceMultiVendor.editableVendorTypes.length; i++) {
                var vendorType = Craft.CommerceMultiVendor.editableVendorTypes[i];

                if (this.getSourceByKey('vendorType:' + vendorType.id)) {
                    this.editableVendorTypes.push(vendorType);
                }
            }

            this.base();
        },

        getDefaultSourceKey: function() {
            // Did they request a specific vendor vendorType in the URL?
            if (this.settings.context === 'index' && typeof defaultVendorTypeHandle !== 'undefined') {
                for (var i = 0; i < this.$sources.length; i++) {
                    var $source = $(this.$sources[i]);

                    if ($source.data('handle') === defaultVendorTypeHandle) {
                        return $source.data('key');
                    }
                }
            }

            return this.base();
        },

        updateButton: function() {
            if (!this.$source) {
                return;
            }

            // Get the handle of the selected source
            var selectedSourceHandle = this.$source.data('handle');

            var i, href, label;

            // Update the New Vendor button
            // ---------------------------------------------------------------------

            if (this.editableVendorTypes.length) {
                // Remove the old button, if there is one
                if (this.$newVendorBtnVendorType) {
                    this.$newVendorBtnVendorType.remove();
                }

                // Determine if they are viewing a vendorType that they have permission to create vendors in
                var selectedVendorType;

                if (selectedSourceHandle) {
                    for (i = 0; i < this.editableVendorTypes.length; i++) {
                        if (this.editableVendorTypes[i].handle === selectedSourceHandle) {
                            selectedVendorType = this.editableVendorTypes[i];
                            break;
                        }
                    }
                }

                this.$newVendorBtnVendorType = $('<div class="btngroup submit"/>');
                var $menuBtn;

                // If they are, show a primary "New vendor" button, and a dropdown of the other vendorTypes (if any).
                // Otherwise only show a menu button
                if (selectedVendorType) {
                    href = this._getVendorTypeTriggerHref(selectedVendorType);
                    label = (this.settings.context === 'index' ? Craft.t('app', 'New vendor') : Craft.t('app', 'New {vendorType} vendor', {vendorType: selectedVendorType.name}));
                    this.$newVendorBtn = $('<a class="btn submit add icon" ' + href + '>' + Craft.escapeHtml(label) + '</a>').appendTo(this.$newVendorBtnVendorType);

                    if (this.settings.context !== 'index') {
                        this.addListener(this.$newVendorBtn, 'click', function(ev) {
                            this._openCreateVendorModal(ev.currentTarget.getAttribute('data-id'));
                        });
                    }

                    if (this.editableVendorTypes.length > 1) {
                        $menuBtn = $('<div class="btn submit menubtn"></div>').appendTo(this.$newVendorBtnVendorType);
                    }
                }
                else {
                    this.$newVendorBtn = $menuBtn = $('<div class="btn submit add icon menubtn">' + Craft.t('app', 'New vendor') + '</div>').appendTo(this.$newVendorBtnVendorType);
                }

                if ($menuBtn) {
                    var menuHtml = '<div class="menu"><ul>';

                    for (i = 0; i < this.editableVendorTypes.length; i++) {
                        var vendorType = this.editableVendorTypes[i];

                        if (this.settings.context === 'index' || vendorType !== selectedVendorType) {
                            href = this._getVendorTypeTriggerHref(vendorType);
                            label = (this.settings.context === 'index' ? vendorType.name : Craft.t('app', 'New {vendorType} vendor', {vendorType: vendorType.name}));
                            menuHtml += '<li><a ' + href + '">' + Craft.escapeHtml(label) + '</a></li>';
                        }
                    }

                    menuHtml += '</ul></div>';

                    $(menuHtml).appendTo(this.$newVendorBtnVendorType);
                    var menuBtn = new Garnish.MenuBtn($menuBtn);

                    if (this.settings.context !== 'index') {
                        menuBtn.on('optionSelect', $.proxy(function(ev) {
                            this._openCreateVendorModal(ev.option.getAttribute('data-id'));
                        }, this));
                    }
                }

                this.addButton(this.$newVendorBtnVendorType);
            }

            // Update the URL if we're on the Categories index
            // ---------------------------------------------------------------------

            if (this.settings.context === 'index' && typeof history !== 'undefined') {
                var uri = 'commerce/vendors';

                if (selectedSourceHandle) {
                    uri += '/' + selectedSourceHandle;
                }

                history.replaceState({}, '', Craft.getUrl(uri));
            }
        },

        _getVendorTypeTriggerHref: function(vendorType) {
            if (this.settings.context === 'index') {
                var uri = 'commerce/vendors/' + vendorType.handle + '/new';
                if (this.siteId && this.siteId != Craft.primarySiteId) {
                    for (var i = 0; i < Craft.sites.length; i++) {
                        if (Craft.sites[i].id == this.siteId) {
                            uri += '/'+Craft.sites[i].handle;
                        }
                    }
                }
                return 'href="' + Craft.getUrl(uri) + '"';
            }
            else {
                return 'data-id="' + vendorType.id + '"';
            }
        },

        _openCreateVendorModal: function(vendorTypeId) {
            if (this.$newVendorBtn.hasClass('loading')) {
                return;
            }

            // Find the vendorType
            var vendorType;

            for (var i = 0; i < this.editableVendorTypes.length; i++) {
                if (this.editableVendorTypes[i].id == vendorTypeId) {
                    vendorType = this.editableVendorTypes[i];
                    break;
                }
            }

            if (!vendorType) {
                return;
            }

            this.$newVendorBtn.addClass('inactive');
            var newVendorBtnText = this.$newVendorBtn.text();
            this.$newVendorBtn.text(Craft.t('app', 'New {vendorType} vendor', {vendorType: vendorType.name}));

            Craft.createElementEditor(this.elementType, {
                hudTrigger: this.$newVendorBtnVendorType,
                elementType: 'batchnz\\craftcommercemultivendor\\elements\\Vendor',
                siteId: this.siteId,
                attributes: {
                    vendorTypeId: vendorTypeId
                },
                onBeginLoading: $.proxy(function() {
                    this.$newVendorBtn.addClass('loading');
                }, this),
                onEndLoading: $.proxy(function() {
                    this.$newVendorBtn.removeClass('loading');
                }, this),
                onHideHud: $.proxy(function() {
                    this.$newVendorBtn.removeClass('inactive').text(newVendorBtnText);
                }, this),
                onSaveElement: $.proxy(function(response) {
                    // Make sure the right vendorType is selected
                    var vendorTypeSourceKey = 'vendorType:' + vendorTypeId;

                    if (this.sourceKey !== vendorTypeSourceKey) {
                        this.selectSourceByKey(vendorTypeSourceKey);
                    }

                    this.selectElementAfterUpdate(response.id);
                    this.updateElements();
                }, this)
            });
        }
    });

// Register it!
Craft.registerElementIndexClass('batchnz\\craftcommercemultivendor\\elements\\Vendor', Craft.CommerceMultiVendor.VendorIndex);
