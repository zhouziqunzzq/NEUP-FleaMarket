@extends('layout.master')

@section('title', "emmmmm...")

@section('content')
<div class="jumbotron">
    <p>先锋市场当前仅允许校内人员注册使用。如果这是你第一次使用本网站，请点击下面的按钮，前往校园统一身份认证服务平台进行认证注册。</p>
    <p>点击下面的按钮即表示您已阅读并同意<a href="/pp">隐私政策</a>和<a href="/tos">服务条款</a>。</p>
    <p><div class="row"><div class="mx-auto"><a href="/sso" class="btn btn-primary">校园统一身份认证服务平台</a></div></div></p>
    <p>友情提示：校园统一身份认证服务平台的密码为校园卡查询密码。注意：此密码与是否修改过卡片的消费密码无关。初始密码可能是以下几种之一：身份证号后六位（“X”用“0”代替），学号后六位，「000000」。为了您的账号安全，强烈建议您修改校园卡查询密码。<a href="http://ecard.neu.edu.cn/selfsearch/login.aspx">点此前往校园卡自助查询平台</a> </p>
    <p>初次登录后，你可以为自己设置一个用户名，或者绑定你的邮箱，以使用它们进行登录。你也可以继续使用校卡平台进行快捷登录。</p>
</div>
@endsection