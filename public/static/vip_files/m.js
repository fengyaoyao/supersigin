var u = navigator.userAgent;
var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Adr') > -1;
var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/);

function setQueryString(key, value, url) {
    url = url ? url : window.location.href;
    if (key && typeof(value) != 'undefined') {
        var urls = url.split('?');
        var new_urls = urls[0];
        var new_querys = new Array();

        if (url.indexOf('?') > 0) {
            var query = urls[1];
            var querys = query.split('&');
            for (var val in querys) {
                if (querys[val].indexOf('=') > 0) {
                    var temp = querys[val].split('=');
                    new_querys[temp[0]] = temp[1];
                }
            }
        }
        new_querys[key] = value;
        var i = 0;
        for (var val in new_querys) {
            if (i != 0) {
                new_urls += '&';
            } else {
                new_urls += '?';
            }
            new_urls += val + '=' + new_querys[val];
            i++;
        }
        return new_urls;
    } else {
        return url;
    }
}

function setDownloadUrl(el) {
    if (isAndroid == true && (typeof install.url.android) == 'string') {
        $(el).attr('href', install.url.android);
    }
    if (isiOS == true && (typeof install.url.ios) == 'string') {
        $(el).attr('href', install.url.ios);
    }
}

function safari(real) {
    if (navigator.userAgent.indexOf('baiduboxapp', 10) > 0 || real) {
        var kuandu = document.documentElement.clientWidth;
        var gaodu = document.documentElement.clientHeight;
        var gaodu1 = document.body.clientHeight;
        var zz = document.getElementById('zz');
        var jump = document.getElementById('baidu_select');
        zz.style.width = kuandu + 'px';
        zz.style.height = gaodu + 'px';
        var top = Math.ceil((gaodu - 165) / 2);
        var left = Math.ceil((kuandu - 248) / 2);
        jump.style.top = top + 'px';
        jump.style.left = left + 'px';
        zz.style.display = 'block';
        jump.style.display = 'block';
        var closed = document.getElementById('jump_closed1');
        closed.onclick = function () {
            jump.style.display = 'none';
            zz.style.display = 'none';
        };
        var url = window.location.href;
        var btn = document.getElementById('btn_copy');
        var test = document.getElementById('select_url');
        test.value = url;
        btn.addEventListener('click', function (evtnt) {
            foo();
        }, false);

        function foo() {
            var size = test.value.length;
            selectText(test, 0, size);
        }

        function selectText(textbox, startIndex, stopIndex) {
            if (textbox.setSelectionRange) {
                textbox.setSelectionRange(startIndex, stopIndex);
            } else if (textbox.createTextRange) {
                var range = textbox.createTextRange();
                range.collapse(true);
                range.moveStart('character', startIndex);
                range.moveEnd('character', stopIndex - startIndex);
                range.select();
            }
            textbox.focus();
        }

        return true;
    } else {
        return false;
    }
}

function installIPA() {
    if ((typeof install.url.ios) == 'string') {
        _hmt.push(['_trackEvent', 'app', 'download_ios', location.href]);
    } else {
        event.preventDefault();
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: install.url.ios[0],
            data: {'game_id': install.id, 'agent': install.agent},
            success: function (data) {
                if (data.status == 0) {
                    alert('第一次下载，正在分包中，请45秒后再试');
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: install.url.ios[1],
                    data: {'game_id': install.id, 'agent': install.agent, 'ajax': 1},
                    success: function (data) {
                        if (data.status == 1) {
                            install.url.ios = data.info;
                            location.href = data.info;
                            setDownloadUrl('.install');
                        } else {
                            alert(data.info);
                        }
                    }
                });
            }
        });
    }
}

$(function () {
    // setDownloadUrl('.install');
});

$(function () {
    // setDownloadUrl('.download');
    $('.download').on('click', function (event) {
        if (isAndroid == true) {
            if (u.match(/MicroMessenger/i) != null || ((typeof install.url.android) != 'string' && u.match(/QQ\//i) != null)) {
                event.preventDefault();
                $('.toBrowser').show();
            } else {
                _hmt.push(['_trackEvent', 'app', 'download_android', location.href]);
                $('#zz').hide();
            }
        }
        if (isiOS == true) {
            event.preventDefault();
            url = location.href;
            url = setQueryString('ios', 1, url);
            url = setQueryString('vip', 1, url);
            location.href = url;
            /*$('.iosTips').delay(2000).show();
            if (!$('.iosTips').hasClass('swiper')) {
                new Swiper('.swiper-container', {
                    centeredSlides: true,
                    autoplay: {
                        delay: 3000,
                        disableOnInteraction: false
                    },
                    pagination: {
                        el: '.swiper-pagination',
                        clickable: true
                    },
                    navigation: {
                        nextEl: '.swiper-button-next',
                        prevEl: '.swiper-button-prev'
                    },
                    loop: true
                });
            }
            $('.iosTips').addClass('swiper');
            $('.iosTipsR').on('click', function () {
                $('.iosTips').hide();
            });*/
        }
    });

    $('.toBrowser').on('click', function () {
        $(this).hide();
    });

    $('.main_visual').hover(function () {
        $('#btn_prev,#btn_next').fadeIn()
    }, function () {
        $('#btn_prev,#btn_next').fadeOut()
    });
    $dragBln = false;

    timer = setInterval(function () {
        $('#btn_next').click();
    }, 5000);

    $('.main_visual').hover(function () {
        clearInterval(timer);
    }, function () {
        timer = setInterval(function () {
            $('#btn_next').click();
        }, 5000);
    });

    $('.main_image').bind('touchstart', function () {
        clearInterval(timer);
    }).bind('touchend', function () {
        timer = setInterval(function () {
            $('#btn_next').click();
        }, 5000);
    });
});