# Nginx 租户站点模板
# 占位符由 deploy_tenant.sh 替换：
#   {{SERVER_NAME}} {{SITE_ROOT}} {{PHP_INCLUDE}} {{ACCESS_LOG}} {{ERROR_LOG}} {{REWRITE_INCLUDE}}

server
{
    listen 80;
    server_name {{SERVER_NAME}};
    index index.php index.html index.htm;
    root {{SITE_ROOT}}/public;

    #PHP-INFO-START
    include {{PHP_INCLUDE}};
    #PHP-INFO-END

    #REWRITE-START
{{REWRITE_BLOCK}}
    #REWRITE-END

    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn|\.project|LICENSE|README.md|clean_db\.py|purge_runtime\.sh|deploy_tenant\.sh|base_template\.sql)
    {
        return 404;
    }

    location ~ \.well-known {
        allow all;
    }

    location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
    {
        expires 30d;
        access_log off;
    }

    location ~ .*\.(js|css)?$
    {
        expires 12h;
        access_log off;
    }

    access_log {{ACCESS_LOG}};
    error_log  {{ERROR_LOG}};
}
