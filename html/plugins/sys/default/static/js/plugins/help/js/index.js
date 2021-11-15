Vue.prototype.__toc = {
    add: function(text, level) {
        var anchor = 'toc_' + level + (++this.index);
        this.toc.push({ anchor: anchor, level: level, text: text });
        return anchor;
    },
    to_html: function() {
        var level_stack = [];
        var result = '';
        var add_start_ul = function() {
            result += '<ul>';
        };
        var add_end_ul = function() {
            result += '</ul>\n';
        };
        var add_li = function(anchor, text) {
            if (text.trim() !== '') {
                result += '<li><a href="#'+anchor+'">'+text+'</a></li>\n';
            }
        };

        this.toc.forEach(function (item) {
            var level_index = level_stack.indexOf(item.level);
            // 没有找到相应level的ul标签，则将li放入新增的ul中
            if (level_index === -1) {
                level_stack.unshift(item.level);
                add_start_ul();
                add_li(item.anchor, item.text);
            } // 找到了相应level的ul标签，并且在栈顶的位置则直接将li放在此ul下
            else if (level_index === 0) {
                add_li(item.anchor, item.text);
            } // 找到了相应level的ul标签，但是不在栈顶位置，需要将之前的所有level出栈并且打上闭合标签，最后新增li
            else {
                while (level_index--) {
                    level_stack.shift();
                    add_end_ul();
                }
                add_li(item.anchor, item.text);
            }
        });
        // 如果栈中还有level，全部出栈打上闭合标签
        while(level_stack.length) {
            level_stack.shift();
            add_end_ul();
        }
        // 清理先前数据供下次使用
        this.toc = [];
        this.index = 0;
        return '<div class="toc">' + result + '</div>';
    },
    toc: [],
    index: 0
};

// 获取GET数据
Vue.prototype.__axios_get = function (url, func, func_err) {
    var that = this;
    var loading = that.$loading({
        lock: true,
        text: '加载中...',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0)'
    });
    axios
        .get(url)
        .then(function (response) {
            loading.close();
            var data = response.data;
            if (data.code === 0) {
                if (typeof func_err === 'function') {
                    func_err(data.msg);
                } else {
                    error_msg(that, '错误提示', data.msg);
                }
            } else if (typeof data === 'string') {
                func_err(data);
            } else {
                if (typeof func === 'function') {
                    func(data.msg, data.data);
                }
            }
        })
        .catch(function (error) { // 请求失败处理
            loading.close();
            if (typeof func_err === 'function') {
                func_err(error);
            } else {
                error_msg(that, '错误提示', error);
            }
        });
};

Vue.prototype.html_encode = function (str) {
    if (str.length === 0) return "";
    str = str.replace(/&/g, "&amp;");
    str = str.replace(/</g, "&lt;");
    str = str.replace(/>/g, "&gt;");
    str = str.replace(/\'/g, "&#39;");
    str = str.replace(/\"/g, "&quot;");
    return str;
}

// 运行上传文件类型
var arrow_exts = ['jpg', 'jpeg', 'gif', 'png', 'ico', 'bmp'];

var image_list = [];
var image_index = -1;

var vue_help = new Vue({
    el: '#app',
    data: function() {
        var renderer_md = new marked.Renderer();
        var that = this;
        renderer_md.heading = function(text, level) {
            var anchor = that.__toc.add(text, level);
            return '<a id="' + anchor + '"></a><h' + level + '>' + text + '</h' + level + '>\n';
        };
        renderer_md.code = function (code) {
            var code_html = code;
            if (typeof hljs !== "undefined") {
                code_html = hljs.highlightAuto(code).value;
            }
            var copy_html = "<div class='el-icon-document-copy code_copy' data-clipboard-text=\"" + that.html_encode(code) + "\"></div>";
            return '<div class="code">' + copy_html + '<pre><code>' + code_html + '</code></pre></div>';
        };

        renderer_md.image = function (href, title, text) {
            var title_html = '';
            if (title) {
                title_html = ' title="' + title + '"'
            }
            return '<div class="code_img" src="' + href + '" alt="' + text + '"' + title_html + '></div>';
        };

        var marked_opt = {
            renderer: renderer_md,
            gfm: true,
            tables: true,
            breaks: true,
            pedantic: false,
            sanitize: false,
            smartLists: true,
            smartypants: false
        };
        marked.setOptions(marked_opt);
        return {
            search: '',
            search_old: '',
            content: '',
            content_html: '',
            is_search_action: false,
            node_key: id,
            node_key_default: id,
            expanded: [id],
            ids: ids,
            prev_info: false,
            next_info: false,
            menu_show: document.body.clientWidth > 600,
            menu_name: '',
            data: JSON.parse(config),
            marked: marked,
            contents: [],
            defaultProps: {
                children: 'children',
                label: 'title'
            },
            pswp: document.querySelectorAll('.pswp')[0]
        };
    },
    mounted: function(){
        if (this.$refs.content !== undefined) {
            var content = this.$refs.content.textContent;
            content = content.replace(RegExp("&#123;&#123;", "g"), "{{");
            this.content = content.replace(RegExp("&#126;&#126;", "g"), "}}");
            this.contents[this.node_key] = this.content;
        }
        this.__reset(is_top);
        if (this.data.length > 0) {
            this.node_key_default = this.data[0].id;
        }
        if (is_top) {
            this.menu_name = '';
        }
    },
    methods: {
        resize: function(){
            this.menu_show = document.body.clientWidth > 600;
        },
        to_default: function(){
            this.node_key = this.node_key_default;
            if (this.$refs.tree !== undefined) {
                this.$refs.tree.setCurrentKey(this.node_key_default);
            }

            this.$nextTick(function () {
                this.__set_root_base();
            });
        },
        __set_root_base: function(){
            window.history.pushState({}, 0, url_root_base);
            this.menu_name = '';
        },
        __reset_data: function(){
            if (this.$refs.tree === undefined) {
                return false;
            }
            var node_key = this.node_key;
            this.menu_name = this.$refs.tree.getCurrentNode()[this.defaultProps.label];
            var ids_len = this.ids.length;
            var find_id = this.ids.indexOf(node_key);
            if (find_id > 0) {
                this.prev_info = this.$refs.tree.getNode(this.ids[find_id - 1]).data;
            } else {
                this.prev_info = false;
            }

            if (find_id < ids_len - 1) {
                this.next_info = this.$refs.tree.getNode(this.ids[find_id + 1]).data;
            } else {
                this.next_info = false;
            }
        },
        __set_content: function(content){
            this.content = content;
        },
        __reset: function(is_top){
            var that = this;
            var content = that.contents[that.node_key];
            var to_url = url_root + that.node_key;
            if (is_top === true) {
                this.__set_root_base();
            } else {
                window.history.pushState({}, 0, to_url);
            }
            if (content === undefined) {
                this.__axios_get(to_url + "?type=json", function (msg, data) {
                    that.contents[that.node_key] = data;
                    that.__reset_data();
                    that.__set_content(data);
                }, function (msg) {
                    that.contents[that.node_key] = msg;
                    that.__reset_data();
                    that.__set_content(msg);
                });
            } else {
                this.__reset_data();
                that.__set_content(content);
            }
        },
        get_replace_content: function(content, reg, req) {
            req = req.replace("#", reg);
            var contents = content.split('<');
            for (var i in contents) {
                var iv = contents[i];
                if (iv.trim() === '') {
                    continue;
                }
                var ind = iv.indexOf('>');
                var str = '<';
                if (ind >= 0) {
                    var left = iv.substring(0, ind + 1);
                    var right = iv.substring(ind + 1);
                    var rights = right.split("&");
                    for (var j in rights) {
                        var jv = rights[j];
                        if (j == 0) {
                            rights[j] = jv.split(reg).join(req);
                            continue;
                        }
                        var pos = jv.indexOf(";");
                        var jv_left = "&";
                        if (pos > 0) {
                            var jvl = jv.substring(0, pos);
                            if (['gt', 'lt', '#39', 'quot'].indexOf(jvl.toLowerCase()) >= 0) {
                                jv_left += jvl + ";";
                                jv = jv.substring(pos + 1);
                            }
                        }
                        rights[j] = jv_left + jv.split(reg).join(req);
                    }
                    str += left + rights.join("");
                } else {
                    str += iv;
                }
                contents[i] = str;
            }
            return contents.join("");
        },
        set_marked: function(){
            this.__toc.toc = [];
            var content = this.marked(this.content);
            content = content.replace(RegExp("<a", "g"), '<a target="_blank"');
            if (content.indexOf('[TOC]') >= 0) {
                content = content.replace(RegExp("\\[TOC\\]", "g"), '');
                content = this.__toc.to_html() + content;
            }

            var search = this.search.trim();
            if (search !== '') {
                search = search.replace(RegExp(">", "g"), "&gt;");
                search = search.replace(RegExp("<", "g"), "&lt;");
                search = search.replace(RegExp("'", "g"), "&#39;");
                search = search.replace(RegExp('"', "g"), "&quot;");
                content = this.get_replace_content(content, search, '<span class="c_search">#</span>');
            }

            this.content_html = content;
            var that = this;
            this.$nextTick(function () {
                that.set_plugins();
            });
        },
        jump: function (_id) {
            if (_id === undefined || this.node_key === _id) {
                return;
            }

            this.node_key = _id;

            if (this.$refs.tree !== undefined) {
                this.$refs.tree.setCurrentKey(_id);
            }
        },

        // 上一篇
        prev: function(){
            this.jump(this.prev_info.id);
        },

        // 下一篇
        next: function(){
            this.jump(this.next_info.id);
        },

        read: function (res) {
            this.jump(res.id);
        },
        search_clear: function () {
            this.search = '';
            this.search_enter();
            this.$refs.search.focus();
        },
        search_enter: function () {
            if (this.search_old.trim() === this.search.trim()){
                return false;
            }
            this.search_old = this.search;

            var that = this;
            var search = this.search.trim();
            if (search === '') {
                this.data = JSON.parse(config);
                this.is_search_action = false;
            } else {
                this.__axios_get(url_root + 'search?keyword=' + that.search, function (msg, data) {
                    that.data = data;
                }, function () {
                    that.data = [];
                });
                this.is_search_action = true;
            }
            this.$nextTick(function () {
                if (that.$refs.tree !== undefined) {
                    that.$refs.tree.setCurrentKey(that.node_key);
                }
            });
            this.set_marked();
        },
        is_search: function () {
            return this.search.trim() !== '';
        },
        set_plugins: function () {
            var btns = document.querySelectorAll('.code_copy');
            var clipboard = new ClipboardJS(btns);

            clipboard.on('success', function(e) {
                e.trigger.innerHTML = '<div>已复制</div>';
                setTimeout(function () {
                    e.trigger.innerHTML = '';
                }, 1000);
            });

            clipboard.on('error', function(e) {
                e.trigger.innerHTML = '<div>复制错误</div>';
                setTimeout(function () {
                    e.trigger.innerHTML = '';
                }, 1000);
            });

            var images = document.querySelectorAll(".code_img");
            if (images.length <= 0) {
                return;
            }

            var that = this;

            image_list = [];
            image_index = -1;

            for (var i in images) {
                var dom = images[i];
                if (typeof dom.hasChildNodes !== "function") {
                    continue;
                }
                var src = dom.getAttribute('src');
                if (src) {
                    src = src.replace(/\"/g, "&quot;");
                } else {
                    src = '';
                }
                var title = dom.getAttribute('title');
                if (title) {
                    title = ' title="' + title.replace(/\"/g, "&quot;") + '"';
                } else {
                    title = '';
                }
                var alt = dom.getAttribute('alt');
                if (alt) {
                    alt = ' alt="' + alt.replace(/\"/g, "&quot;") + '"';
                } else {
                    alt = '';
                }

                var template;
                if (is_debug) {
                    // 调试时可上传文件
                    var code_cmd = '';
                    var is_arrow = true;
                    var pos = src.lastIndexOf(".");
                    if (src.indexOf(":") > 0 || src.indexOf("..") > 0 || pos < 0) {
                        is_arrow = false;
                    } else {
                        var ext = src.substring(pos + 1).toLowerCase();
                        if (arrow_exts.indexOf(ext) < 0) {
                            is_arrow = false;
                        }
                    }

                    if (is_arrow && src[0] + src[1] == '//') {
                        is_arrow = false;
                    }

                    // 不运行上传
                    if (!is_arrow) {
                        template = '<img src="' + src + '"/>';
                        template = '<div><div class="code_src"><div class="cmd_text">SRC:</div><div class="cmd_src">' + src + '</div></div><div>' + template + '</div></div>';
                        dom.innerHTML = template;
                        continue;
                    }

                    // 处理上传文件
                    template = '<div>';
                    template += '<el-upload class="code_src" action="/upload" :show-file-list="false" :before-upload="before" :on-success="success" :data="{path: src}" drag>';
                    template += '   <div class="el-upload__text">拖拽此处 / 点击上传</div>';
                    template += '   <div slot="tip" class="cmd_src"><div class="btn" @click="paste" v-html="select ?  ctrl_str : start_str"></div>{{src}}</div>';
                    template += '</el-upload>';
                    template += '<div><img :src="src + \'?\' + cache"/></div>';
                    template += '</div>';
                    var ImageComponent = Vue.extend({
                        template: template,
                        data: function () {
                            var ie_msg = "IE不支持粘贴";
                            return {
                                src: src,
                                cache: (new Date()).getTime(),
                                select: false,
                                index: i,
                                start_str: is_ie ? ie_msg : "开启粘贴",
                                ctrl_str: is_ie ? ie_msg : "<span>请按 Ctrl + v</span>",
                            };
                        },
                        methods: {
                            verify: function(file_name) {
                                pos = file_name.lastIndexOf(".");
                                if (pos < 0) {
                                    this.$message.error('未知类型文件');
                                    return false;
                                }

                                var file_ext = file_name.substring(pos + 1).toLowerCase();
                                if (arrow_exts.indexOf(file_ext) < 0) {
                                    this.$message.error('非图片格式文件');
                                    return false;
                                }

                                if (this.src !== this.src.toLowerCase()) {
                                    this.$message.error('保存路径不能包含大写字母');
                                    return false;
                                }

                                pos = this.src.lastIndexOf(".");
                                var src_ext = this.src.substring(pos + 1).toLowerCase();
                                if (pos < 0 || src_ext !== file_ext) {
                                    if (['ico', 'bmp'].indexOf(file_ext) >= 0) {
                                        this.$message.error("无法转换" + file_ext + "文件");
                                        return false;
                                    }
                                }

                                return true;
                            },

                            before: function(file) {
                                if (!this.verify(file.name)) {
                                    return false;
                                }
                            },

                            success: function (res) {
                                if (typeof res !== 'object') {
                                    return;
                                }

                                if (res.code == 1) {
                                    this.$message.success(res.msg);
                                    this.cache = (new Date()).getTime();
                                } else {
                                    this.$message.error(res.msg);
                                }
                            },

                            reset: function() {
                                this.select = false;
                            },

                            paste: function (event) {
                                if (image_index === this.index) {
                                    this.select = !this.select;
                                    if (this.select) {
                                        image_index = this.index;
                                    } else {

                                        image_index = -1;
                                    }
                                    return;
                                }

                                if (image_index >= 0 && image_list[image_index] !== undefined) {
                                    image_list[image_index].reset();
                                }
                                image_index = this.index;
                                this.select = true;
                            }
                        }
                    });
                    var component = new ImageComponent().$mount();
                    image_list.push(component);
                    dom.appendChild(component.$el);
                    continue;
                }

                template = '<img src="' + src + '"' + alt + title + ' :preview-src-list=\'["' + src + '"]\' @click="show" />';
                var ImageComponent = Vue.extend({
                    template: template,
                    data: {
                        src: src
                    },
                    methods: {
                        show: function () {
                            var img = new Image();
                            img.src = this.$el.currentSrc;
                            img.onload = function() {
                                var items = [
                                    {
                                        src: this.src,
                                        w: this.width,
                                        h: this.height
                                    }
                                ];
                                var options = {
                                    index: 0,
                                    showAnimationDuration: 0,
                                    mainClass: 'pswp--minimal--dark',
                                    captionEl: false,
                                    fullscreenEl: false,
                                    shareEl: false,
                                    bgOpacity: 0.85,
                                    tapToClose: true,
                                    tapToToggleControls: false
                                };
                                var gallery = new PhotoSwipe( that.pswp, PhotoSwipeUI_Default, items, options);
                                gallery.init();
                            };
                            img.onerror = function() {
                                // console.log(this);
                            };
                        }
                    }
                });
                var component = new ImageComponent().$mount();
                dom.appendChild(component.$el);
            }
        }
    },
    watch: {
        // 选择变更
        node_key: function () {
            this.__reset();
            window.scroll({
                top: 0
            });
        },
        // 内容改变设置
        content: function () {
            this.set_marked();
        },
        // 根据菜单设置标题
        menu_name: function () {
            var title_list = [];
            if (typeof this.menu_name === 'string' && this.menu_name !== '') {
                title_list.push(this.menu_name);
            }

            if (main_title !== '') {
                title_list.push(main_title);
            }

            document.title = title_list.join(" - ");
        },
        search: function (value) {
            if (value.length > 100) {
                this.search = value.substring(0, 100);
            }
        }
    }
});

// 监听 Ctrl + 上下左右键，翻页
document.onkeyup = function(event){
    var e = event || window.event || arguments.callee.caller.arguments[0];
    if (e.ctrlKey) {
        if (e.keyCode == 37 || e.keyCode == 38) {
            vue_help.prev();
        } else if(e.keyCode == 39 || e.keyCode == 40) {
            vue_help.next();
        }
    }
};

// 监听调试上传图片
if (is_debug && !is_ie) {
    document.onpaste = function(event){
        if (document.activeElement.localName === 'input') {
            return;
        }

        if (image_index < 0) {
            return;
        }

        var img = image_list[image_index];
        if (img === undefined) {
            return;
        }

        var items = (event.clipboardData || event.originalEvent.clipboardData).items;
        if (items.length <= 0) {
            img.$message.error("剪切板无效");
            return;
        }

        var item = undefined;
        for (var i in items) {
            if (items[i].kind === 'file') {
                item = items[i];
                break;
            }
        }

        if (item === undefined) {
            img.$message.error("剪切板没有图片信息");
            return;
        }

        var file = item.getAsFile();

        if (!img.verify(file.name)) {
            return;
        }

        if (item.type.indexOf("image") == -1) {
            img.$message.error("必须是图片格式文件");
        }

        var formData = new FormData();
        formData.append('path', img.src);
        formData.append('file', file);

        axios.post("/upload", formData).then(function (res) {
            img.success(res.data);
        }).catch(function (error) {
            img.$message.error(error);
        });
    }
}