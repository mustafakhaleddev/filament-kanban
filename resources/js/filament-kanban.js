import Sortable from 'sortablejs'

export default function kanbanBoard({
    hasOrderColumn = false,
    hasCardAction = false,
    collapsible = false,
    dragConstraints = {},
} = {}) {
    const hasDragConstraints = Object.keys(dragConstraints).length > 0

    return {
        sortableInstances: [],
        dragStarted: false,

        init() {
            this.initSortable()
            this.initClickHandler()

            this.$wire.$on('kanban-refresh', () => {
                this.$nextTick(() => this.initSortable())
            })
        },

        initClickHandler() {
            if (!hasCardAction) return

            this.$el.addEventListener('click', (e) => {
                if (this.dragStarted) return

                const card = e.target.closest('[data-kanban-card]')
                if (!card) return

                // Don't trigger card action if clicking a footer action button
                if (e.target.closest('[wire\\:click\\.stop]')) return

                this.$wire.mountAction('kanbanCardClick', {
                    record: card.dataset.recordId,
                })
            })
        },

        destroySortable() {
            this.sortableInstances.forEach((instance) => {
                if (instance && instance.destroy) {
                    instance.destroy()
                }
            })
            this.sortableInstances = []
        },

        initSortable() {
            this.destroySortable()

            const columns = this.$el.querySelectorAll('[data-kanban-column]')

            columns.forEach((column) => {
                const instance = Sortable.create(column, {
                    group: {
                        name: 'kanban',
                        put: (to, from, dragEl) => {
                            if (!hasDragConstraints) return true

                            const sourceColumn =
                                from.el.dataset.columnValue
                            const targetColumn = to.el.dataset.columnValue
                            const allowed =
                                dragConstraints[sourceColumn]

                            if (!allowed) return true

                            return allowed.includes(targetColumn)
                        },
                    },
                    animation: 150,
                    ghostClass: 'fi-kanban-card-ghost',
                    dragClass: 'fi-kanban-card-drag',
                    chosenClass: 'fi-kanban-card-chosen',
                    draggable: '[data-kanban-card]',
                    fallbackOnBody: true,
                    swapThreshold: 0.65,

                    onStart: () => {
                        this.dragStarted = true
                    },

                    onEnd: (evt) => {
                        setTimeout(() => {
                            this.dragStarted = false
                        }, 0)

                        const recordId = evt.item.dataset.recordId
                        const newColumnValue = evt.to.dataset.columnValue
                        const oldColumnValue =
                            evt.from.dataset.columnValue

                        if (
                            newColumnValue === oldColumnValue &&
                            !hasOrderColumn
                        ) {
                            return
                        }

                        const orderedIds = Array.from(
                            evt.to.querySelectorAll('[data-kanban-card]'),
                        ).map((card) => card.dataset.recordId)

                        this.$wire.moveRecord(
                            recordId,
                            newColumnValue,
                            orderedIds,
                        )
                    },
                })

                this.sortableInstances.push(instance)
            })
        },
    }
}
