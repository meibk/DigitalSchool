define(function(require, exports, module) {
    var Widget = require('widget');
    var Notify = require('common/bootstrap-notify');

    var schedule = Widget.extend({
        sunday: null,
        year: null,
        month: null,
        daysInMonth: [31,28,31,30,31,30,31,31,30,31,30,31],
        placeholder: null,
        attrs: {
            saveUrl: null,
            resetUrl: null
        },
        events: {
            "click li.plus": "expand",
            "click li.minus": "collapse",
            "click .next-week": "nextWeek",
            "click .previous-week": "previousWeek",
            "click .next-month": "nextMonth",
            "click .previous-month": "previousMonth",
            'click .course-display .mode': 'changeMode',
            "click button.lesson-remove": "removeLesson",
            "click .gotolesson": "goTolesson",
            "change select.viewType": "changeView"
        },
        setup: function() {
            this.sunday = this.element.find('.sunday').data('day');
            this.set('saveUrl', this.element.find('.schedule').data('save'));
            this.set('resetUrl', this.element.find('.schedule').data('reset'));
            var sunday = this.sunday + '';
            this.year = sunday.substr(0,4);
            this.month = sunday.substr(4,2);
            this.element.find('.changeMonth') && this.changeYearMonth();
            this.giveLessonColor();
            this.bindTableSortable();
            this.bindCourseItemSortable();
            
        },
        bindSortableEvent: function() {
            var self = this;
            $(".schedule-course-item-list").each(function(){
                $(this).sortable("enable");
            });
            var lessonSort = $(".schedule-lesson-list").sortable({
                group:'schedule-sort',
                drag:false,
                itemSelector:'.lesson-item',
                onDragStart: function ($item, container, _super) {
                    // Duplicate $items of the no drop area
                    $item.addClass('draging');
                    if(!container.options.drop){
                        var $placeholder = $item.clone();
                        self.placeholder = $placeholder;
                        $placeholder.insertAfter($item);
                    }
                    _super($item);
                },
                onDrop: function ($item, container, _super, event) {
                    var $template = $('<li data-id="'+$item.data('id')+'" data-url="'+$item.find('a').attr('href')+'"><div class="item-div gotolesson" data-url="'+$item.find('a').attr('href')+'"><div class="thumbnail"><button type="button" class="close lesson-remove"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button><div class="lesson-title">测试试卷</div></div></div></li>');
                    var color = 'schedule-color' + self.hashCode($item.data('ctitle'));
                    $template.find('.thumbnail').addClass(color);
                    $template.find('.lesson-title').html($item.data('title')).attr('title', '来自'+$item.data('ctitle')+'课程');
                    $item.prop('outerHTML', $template.prop("outerHTML"));

                    _super($item);

                    var $placeholder = self.placeholder;
                        $placeholder.removeClass('draging');
                    var result = self.serializeContainer(container.el);
                    self.save(result);
                }
            });
            var courseSort = $(".schedule-course-item-list").sortable({
                distance:30,
                group:'schedule-sort',
                pullPlaceholder:false,
                drop:false
            });
        },
        changeYearMonth: function() {
            var sunday = this.sunday + '';
            var newYearMonth = sunday.substr(0,4) + ' 年' + sunday.substr(4,2) + ' 月';
            this.element.find('.yearMonth').html(newYearMonth);
            this.year = sunday.substr(0,4);
            this.month = sunday.substr(4,2);
        },
        expand: function(e) {
            var target = e.currentTarget;
            $(target).removeClass('plus').addClass('minus');
            $(target).find('.glyphicon').removeClass('glyphicon-plus-sign').addClass('glyphicon-minus-sign');
            $(target).find('.schedule-course-item-list-wrap').addClass('show').removeClass('hidden');
            
        },
        collapse: function(e) {
            var target = e.currentTarget;
            $(target).removeClass('minus').addClass('plus');
            $(target).find('.glyphicon').removeClass('glyphicon-minus-sign').addClass('glyphicon-plus-sign');
            $(target).find('.schedule-course-item-list-wrap').addClass('hidden').removeClass('show');
        },
        save: function(data){
            $.post(this.get('saveUrl'), data, function(){

            });
        },
        removeLesson: function(e) {
            e.stopPropagation();
            var $button = $(e.currentTarget),
                $a = $button.parent().parent();
                $li = $button.parent().parent().parent(),
                $ul = $li.parent();
            $li.remove();
            var result = this.serializeContainer($ul);
            this.save(result);
        },
        goTolesson: function(e) {
            window.open($(e.currentTarget).data('url'));
        },
        sortableAPI: function(selector, API) {
            $(selector).each(function(){
                $(this).sortable(API);
            });
        },
        bindTableSortable: function() {
            var self = this;
            $(".schedule-lesson-list").sortable({
                group:'schedule-sort',
                drag:false,
                itemSelector:'.lesson-item',
                onDragStart: function ($item, container, _super) {
                    // Duplicate $items of the no drop area
                    $item.addClass('draging');
                    if(!container.options.drop){
                        var $placeholder = $item.clone();
                        self.placeholder = $placeholder;
                        $placeholder.insertAfter($item);
                    }
                    _super($item);
                },
                onDrop: function ($item, container, _super, event) {
                    var $template = $('<li data-id="'+$item.data('id')+'" data-url="'+$item.find('a').attr('href')+'"><div class="item-div gotolesson" data-url="'+$item.find('a').attr('href')+'"><div class="thumbnail"><button type="button" class="close lesson-remove"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button><div class="lesson-title">测试试卷</div></div></div></li>');
                    var color = 'schedule-color' + self.hashCode($item.data('ctitle'));
                    $template.find('.thumbnail').addClass(color);
                    $template.find('.lesson-title').html($item.data('title')).attr('title', '来自'+$item.data('ctitle')+'课程');
                    $item.prop('outerHTML', $template.prop("outerHTML"));

                    _super($item);

                    var $placeholder = self.placeholder;
                        $placeholder.removeClass('draging');
                    var result = self.serializeContainer(container.el);
                    self.save(result);
                }
            });
        },
        bindCourseItemSortable: function() {
            $(".schedule-course-item-list").sortable({
                            distance:30,
                            group:'schedule-sort',
                            pullPlaceholder:false,
                            drop:false
            });
        },
        serializeData: function(object) {
            var result = {};
            for (var i = 0; i < object.length; i++) {
                object[i].children().each(function(index){
                    var one = {
                    id: $(this).data('id'),
                    day: object[i].data('day')
                   }
                   result[index] = one;
                });
           }
           return result;
        },
        serializeContainer: function(element) {
            var result = {
                day:element.data('day')
            };
            var ids = '';
            element.children().each(function(){
                ids += $(this).data('id') + ',';
            });
            result.ids = ids.substr(0,ids.length - 1);
            return result;
        },
        changeView: function(e) {
            $(e.currentTarget).val() == 'week' ? this.reset({'sunday': this.sunday,'previewAs':'week'}) 
                : this.reset({'year':this.year,'month':this.month,'previewAs':'month'}); 
        },
        nextWeek: function() {
            var sunday = this.nextSunday(true);
            this.reset({'sunday': sunday,'previewAs':'week',mode:this.mode});
        },
        previousWeek: function() {
            var sunday = this.nextSunday(false);
            this.reset({'sunday': sunday,'previewAs':'week',mode:this.mode});
        },
        nextMonth: function() {
            var cYear = parseInt(this.year, 10),
            cMonth =  parseInt(this.month, 10),
            nextMonth = cMonth + 1 == 13 ? 1: cMonth + 1,
            nextYear = nextMonth == 1 ? cYear + 1 : cYear;
            this.year = nextYear;
            this.month = nextMonth>=10 ? nextMonth:'0'+nextMonth;
            this.element.find('span.yearMonth').html(this.year + ' 年' + this.month + ' 月');
            this.element.find('.viewType').val('month');
            this.reset({'year':this.year,'month':this.month,'previewAs':'month'});
        },
        previousMonth: function() {
            var cYear = parseInt(this.year, 10),
            cMonth =  parseInt(this.month, 10),
            previousMonth = cMonth - 1 == 0 ? 12 : cMonth - 1,
            previousYear = previousMonth == 12 ? cYear - 1 : cYear;
            this.year = previousYear;
            this.month = previousMonth>=10 ? previousMonth:'0'+previousMonth;
            this.element.find('span.yearMonth').html(this.year + ' 年' + this.month + ' 月');
            this.element.find('.viewType').val('month');
            this.reset({'year':this.year,'month':this.month,'previewAs':'month'});
        },
        changeMode: function(e) {
            var $span = $(e.currentTarget),
                self = this,
                mode = $span.hasClass('editable') ? 'normal' : 'edit';
                this.mode = mode;

            if(mode == 'normal') {
                $('#schedule-course-list-editing-panel').hide();
                $('#schedule-course-list-viewing-panel').show(); 
            } 
            
            if(mode == 'edit') {
                $('#schedule-course-list-editing-panel').show();
                $('#schedule-course-list-viewing-panel').hide(); 
            }                
               
            this.reset({'sunday': self.sunday,'previewAs':'week', mode:mode});  
        },
        nextSunday: function(plus) {
            var sunday = this.sunday +'';
            sunday = sunday.substr(0,4) + '/' + sunday.substr(4,2) + '/' + sunday.substr(6,2);
            var offset = plus ? 7 * 24 * 60 * 60 * 1000 : -7 * 24 * 60 * 60 * 1000;
            var nextSunday = new Date(new Date(sunday).getTime() + offset);
            var year = nextSunday.getFullYear();
            var month = nextSunday.getMonth() + 1;
            month = month >= 10 ? month : '0' + month;
            var day = nextSunday.getDate();
            day = day >= 10 ? day : '0' + day;  
            
            sunday = '' + year + month + day;
            this.sunday = sunday;
            return sunday;
        },
        reset: function(data) {
            var self = this;
            $.ajax({
                url: self.get('resetUrl'),
                data: data,
                success: function(html) {
                    self.sortableAPI('.schedule-lesson-list', 'disable');
                    self.element.find('.schedule-body').html(html);
                    
                    if(data.previewAs == 'month') {
                        self.sortableAPI('.schedule-course-item-list', 'disable');
                        self.popover();
                    } else {
                        self.element.find('.viewType').val('week');
                        self.element.find('changeMonth') && self.changeYearMonth();
                        self.bindSortableEvent();
                        self.giveLessonColor();
                        self.sortableAPI('.schedule-course-item-list', 'enable');  
                    }
                }
            });  
        },
        giveLessonColor: function() {
            var self = this;
            this.element.find('.schedule-calendar-week .item-div .thumbnail').each(function(){
                $(this).addClass('schedule-color' + self.hashCode($(this).data('title')));
            });
        },
        hashCode: function(string) {
            var hashCode = 0;
            if(string) {
                for (var i = string.length - 1; i >= 0; i--) {
                    hashCode = hashCode + string.charCodeAt(i);
                };
                return hashCode % 15 + 1;
            } else {
                return 0;
            }

        },
        popover: function() {
            $('.schedule').popover({
                selector: 'td',
                container: '.popover-container',
                trigger: 'hover',
                placement: 'auto',
                html: true,
                delay: 200,
                content: function() {
                    $(this).addClass('currentPopover');
                    return $(this).find('.popover-content').html();
                },
            });

            
            $('.schedule').on('mouseenter', '.popover', function(){

                $(this).addClass('keep-hovering');
            });

            $('.schedule').on('mouseleave', '.popover', function() {
                $(this).removeClass('keep-hovering');
                $('.schedule').find('.currentPopover').removeClass('currentPopover').popover('hide');
            });

            $('.schedule').on('hide.bs.popover', function () {
                if ($(this).find('.popover').hasClass('keep-hovering')) {
                    return false;
                }
                return true;
            });    
        }

    });

    module.exports = schedule;
});