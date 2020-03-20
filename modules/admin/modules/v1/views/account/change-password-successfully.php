<?php
$this->registerJs(<<<JS
alert('修改成功，请重新登陆');
window.location.href = '{$redirectUrl}';
JS
);