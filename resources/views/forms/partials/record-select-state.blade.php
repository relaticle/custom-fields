{{-- The picker's behaviour, shared by every flavor: a flavor decides what the picker looks
     like and never what it does, so this object is written once and included by both views. --}}
{
    state: $wire.{!! $applyStateBindingModifiers("\$entangle('{$statePath}')") !!},
    open: false,
    search: '',
    isSearching: false,
    searchResults: [],
    componentKey: @js($key),
    allowMultiple: @js($allowMultiple),
    maxValues: @js($maxValues),
    isDisabled: @js($isDisabled),
    recordsCache: @js($initialRecords),
    initialOptions: @js(array_values($initialOptions)),
    maxVisibleValues: @js($maxVisiblePills),
    minSearchLength: @js($minSearchLength),
    checksHolderConflicts: @js($checksHolderConflicts),
    overflowLabels: @js($overflowLabels),
    countLabels: @js($countLabels),
    confirmedStealIds: @js($confirmedStealIds),
    pendingSteal: null,
    selectedSnapshot: [],
    activeIndex: -1,
    documentClickListener: null,

    init() {
        this.commit(this.ids.filter(v => v || v === 0));

        this.$watch('search', (value) => {
            if (value.trim().length >= this.minSearchLength) {
                this.performSearch();
            } else {
                this.searchResults = [];
            }
            this.activeIndex = this.sortedOptions.length > 0 ? 0 : -1;
        });

        this.$watch('open', (isOpen) => {
            if (isOpen) {
                this.selectedSnapshot = [...this.ids];
                this.search = '';
                this.searchResults = [];
                this.activeIndex = this.getInitialActiveIndex();
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus();
                    this.scrollActiveIntoView();
                });
            } else {
                // When closing in multi-select, reorder state to match visual order
                if (this.allowMultiple) {
                    const snapshotSelected = this.selectedSnapshot.filter(id => this.ids.includes(id));
                    const newlySelected = this.ids.filter(id => !this.selectedSnapshot.includes(id));
                    this.commit([...snapshotSelected, ...newlySelected]);
                }
                this.search = '';
                this.searchResults = [];
                this.activeIndex = -1;
                this.pendingSteal = null;
            }
        });

        this.documentClickListener = (event) => {
            if (this.open && !this.$el.contains(event.target)) {
                this.close();
            }
        };
        document.addEventListener('click', this.documentClickListener);
    },

    destroy() {
        if (this.documentClickListener) {
            document.removeEventListener('click', this.documentClickListener);
        }
    },

    // The state is a list until a move is confirmed, and a map from then on, so every read
    // goes through here and every write through commit().
    get ids() {
        if (Array.isArray(this.state)) {
            return this.state;
        }

        return Array.isArray(this.state?.ids) ? this.state.ids : [];
    },

    // Each confirmation names the record it was given for, so it holds only while that
    // record is in the payload and never answers for one added after it.
    commit(ids) {
        this.confirmedStealIds = this.confirmedStealIds.filter(id => ids.includes(id));

        this.state = this.confirmedStealIds.length > 0
            ? { ids: ids, confirmed: [...this.confirmedStealIds] }
            : ids;
    },

    // A count the reader can act on, in the plural form the locale picked server-side.
    countLabel(labels, count) {
        return (count === 1 ? labels.one : labels.many).replace(':count', count);
    },

    get activeDescendant() {
        if (!this.open || this.activeIndex < 0 || this.activeIndex >= this.sortedOptions.length) {
            return null;
        }
        return this.$id('option-' + this.activeIndex);
    },

    getInitialActiveIndex() {
        if (!this.hasValues) return 0;
        const firstSelectedIndex = this.sortedOptions.findIndex(opt => this.ids.includes(opt.id));
        return firstSelectedIndex >= 0 ? firstSelectedIndex : 0;
    },

    get canAddMore() {
        if (!this.allowMultiple) {
            return this.ids.length === 0;
        }
        return this.ids.length < this.maxValues;
    },

    get hasValues() {
        return this.ids.length > 0;
    },

    get selectedRecords() {
        // When the dropdown is open in multi-select, the snapshot keeps the visible order
        // steady while selections change underneath it.
        if (this.open && this.allowMultiple) {
            const snapshotSelected = this.selectedSnapshot.filter(id => this.ids.includes(id));
            const newlySelected = this.ids.filter(id => !this.selectedSnapshot.includes(id));
            const orderedIds = [...snapshotSelected, ...newlySelected];
            return orderedIds.map(id => this.recordsCache[id] || { id, label: id, avatar: null }).filter(Boolean);
        }
        return this.ids.map(id => this.recordsCache[id] || { id, label: id, avatar: null }).filter(Boolean);
    },

    get visibleRecords() {
        return this.selectedRecords.slice(0, this.maxVisibleValues);
    },

    get hiddenCount() {
        return Math.max(0, this.selectedRecords.length - this.maxVisibleValues);
    },

    get sortedOptions() {
        const searchLower = this.search.toLowerCase().trim();

        if (searchLower.length >= this.minSearchLength && this.searchResults.length > 0) {
            return this.sortBySelected([...this.searchResults]);
        }

        let options = [...this.initialOptions];
        if (searchLower) {
            options = options.filter(opt => opt.label.toLowerCase().includes(searchLower));
        }

        return this.sortBySelected(options);
    },

    sortBySelected(options) {
        const selectedIds = this.allowMultiple ? this.selectedSnapshot : this.ids;
        return options.sort((a, b) => {
            const aSelected = selectedIds.includes(a.id);
            const bSelected = selectedIds.includes(b.id);
            if (aSelected && !bSelected) return -1;
            if (!aSelected && bSelected) return 1;
            if (aSelected && bSelected) {
                return selectedIds.indexOf(a.id) - selectedIds.indexOf(b.id);
            }
            return 0;
        });
    },

    isSelected(recordId) {
        return this.ids.includes(recordId);
    },

    get emptyStateMessage() {
        const searchLength = this.search.trim().length;
        if (searchLength >= this.minSearchLength) {
            return @js(__('custom-fields::custom-fields.record.no_results'));
        }
        if (searchLength > 0) {
            return @js($shortSearchMessage);
        }
        if (this.initialOptions.length === 0) {
            return @js(__('custom-fields::custom-fields.record.none_available'));
        }
        return '';
    },

    async performSearch() {
        const query = this.search.trim();

        if (query.length < this.minSearchLength) {
            this.searchResults = [];
            return;
        }

        this.isSearching = true;

        try {
            const results = await $wire.callSchemaComponentMethod(
                this.componentKey,
                'getSearchResultsForJs',
                { search: query }
            );
            this.searchResults = Array.isArray(results) ? results : Object.values(results || {});
        } catch {
            this.searchResults = [];
        } finally {
            this.isSearching = false;
        }
    },

    // A record already held by someone else is confirmed before the writer resolves it, and
    // the sentence shown is the one the writer would have refused with.
    async holderConflictFor(recordId) {
        if (!this.checksHolderConflicts || this.confirmedStealIds.includes(recordId)) {
            return null;
        }

        try {
            return await $wire.callSchemaComponentMethod(
                this.componentKey,
                'holderConflictFor',
                { recordId: String(recordId) }
            );
        } catch {
            return null;
        }
    },

    async confirmSteal() {
        const pending = this.pendingSteal;

        if (!pending) return;

        this.confirmedStealIds = [...this.confirmedStealIds, pending.record.id];
        this.pendingSteal = null;

        await this.selectRecord(pending.record);
    },

    cancelSteal() {
        this.pendingSteal = null;
    },

    toggle() {
        if (this.isDisabled) return;
        this.open ? this.close() : this.openPanel();
    },

    openPanel() {
        if (this.isDisabled || this.open) return;
        this.$refs.panel?.open(this.$refs.trigger);
        this.open = true;
    },

    close() {
        if (!this.open) return;
        this.$refs.panel?.close();
        this.open = false;
        this.$refs.trigger?.focus();
    },

    closePanel() {
        this.close();
    },

    onKeydown(event) {
        if (this.isDisabled) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                event.stopPropagation();
                if (this.open) {
                    this.focusNext();
                } else {
                    this.openPanel();
                }
                break;
            case 'ArrowUp':
                event.preventDefault();
                event.stopPropagation();
                if (this.open) {
                    this.focusPrevious();
                } else {
                    this.openPanel();
                }
                break;
            case 'Home':
                if (this.open) {
                    event.preventDefault();
                    this.focusFirst();
                }
                break;
            case 'End':
                if (this.open) {
                    event.preventDefault();
                    this.focusLast();
                }
                break;
            case 'Enter':
                event.preventDefault();
                if (this.open && this.activeIndex >= 0 && this.activeIndex < this.sortedOptions.length) {
                    const record = this.sortedOptions[this.activeIndex];
                    this.allowMultiple ? this.toggleRecord(record) : this.selectRecord(record);
                } else if (!this.open) {
                    this.openPanel();
                }
                break;
            case ' ':
                if (document.activeElement === this.$refs.searchInput) {
                    return;
                }
                if (!this.open) {
                    event.preventDefault();
                    this.openPanel();
                }
                break;
            case 'Tab':
                if (this.open) {
                    this.close();
                }
                break;
        }
    },

    onSearchKeydown(event) {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                event.stopPropagation();
                this.focusNext();
                break;
            case 'ArrowUp':
                event.preventDefault();
                event.stopPropagation();
                this.focusPrevious();
                break;
            case 'Home':
                event.preventDefault();
                this.focusFirst();
                break;
            case 'End':
                event.preventDefault();
                this.focusLast();
                break;
            case 'Enter':
                event.preventDefault();
                event.stopPropagation();
                if (this.activeIndex >= 0 && this.activeIndex < this.sortedOptions.length) {
                    const record = this.sortedOptions[this.activeIndex];
                    this.allowMultiple ? this.toggleRecord(record) : this.selectRecord(record);
                } else if (this.sortedOptions.length > 0) {
                    const record = this.sortedOptions[0];
                    this.allowMultiple ? this.toggleRecord(record) : this.selectRecord(record);
                }
                break;
            case 'Escape':
                event.preventDefault();
                event.stopPropagation();
                this.closePanel();
                break;
        }
    },

    focusNext() {
        const max = this.sortedOptions.length - 1;
        if (max < 0) return;
        this.activeIndex = this.activeIndex >= max ? 0 : this.activeIndex + 1;
        this.scrollActiveIntoView();
    },

    focusPrevious() {
        const max = this.sortedOptions.length - 1;
        if (max < 0) return;
        this.activeIndex = this.activeIndex <= 0 ? max : this.activeIndex - 1;
        this.scrollActiveIntoView();
    },

    focusFirst() {
        if (this.sortedOptions.length === 0) return;
        this.activeIndex = 0;
        this.scrollActiveIntoView();
    },

    focusLast() {
        if (this.sortedOptions.length === 0) return;
        this.activeIndex = this.sortedOptions.length - 1;
        this.scrollActiveIntoView();
    },

    scrollActiveIntoView() {
        this.$nextTick(() => {
            const activeOption = this.$refs.optionsList?.querySelector('[data-highlighted]');
            if (activeOption) {
                activeOption.scrollIntoView({ block: 'nearest' });
            }
        });
    },

    announceSelection(record, wasSelected) {
        if (this.$refs.announcer) {
            const action = wasSelected ? @js(__('custom-fields::custom-fields.record.announce_deselected')) : @js(__('custom-fields::custom-fields.record.announce_selected'));
            let message = record.label + ' ' + action;
            if (this.allowMultiple) {
                message += '. ' + this.countLabel(this.countLabels, this.ids.length);
            }
            this.$refs.announcer.textContent = message;
        }
    },

    // A selection can wait on the server's answer about the record's current holder, so the
    // announcement is made once the selection has landed, never before it.
    async toggleRecord(record) {
        if (this.isSelected(record.id)) {
            this.removeRecord(record.id);
            this.announceSelection(record, true);

            return;
        }

        await this.selectRecord(record);

        if (this.isSelected(record.id)) {
            this.announceSelection(record, false);
        }
    },

    async selectRecord(record) {
        if (this.allowMultiple && !this.canAddMore) return;

        if (this.ids.includes(record.id)) return;

        const conflict = await this.holderConflictFor(record.id);

        if (conflict) {
            this.pendingSteal = { record: record, message: conflict };
            return;
        }

        this.recordsCache[record.id] = {
            id: record.id,
            label: record.label,
            avatar: record.avatar,
            avatarShape: record.avatarShape,
            provenance: record.provenance ?? null
        };

        if (this.allowMultiple) {
            this.commit([...this.ids, record.id]);
        } else {
            this.commit([record.id]);
            this.closePanel();
        }
    },

    removeRecord(recordId) {
        this.commit(this.ids.filter(id => id !== recordId));
    },

    // The order the chips are left in is the order the links are written in, so the snapshot
    // moves with the payload and the open panel keeps reading the same list.
    moveRecord(recordId, offset) {
        const ids = this.selectedRecords.map(record => record.id);
        const from = ids.indexOf(recordId);
        const to = from + offset;

        if (from < 0 || to < 0 || to >= ids.length) {
            return;
        }

        ids.splice(to, 0, ids.splice(from, 1)[0]);

        this.selectedSnapshot = [...ids];
        this.commit(ids);
    }
}
