# Pinned official PHP image; all compilation uses tools bundled in the base.
# No application source, credentials, Composer packages or mutable apt repos.
FROM php@sha256:64ffb7411253121bbeb0d9eba37ce85e88d2a60d698e5debbd66bac71b20f3c2
RUN docker-php-ext-install -j2 pdo_mysql pcntl \
    && php -r 'foreach (["pdo_mysql", "pcntl", "curl", "mbstring", "dom"] as $e) { if (!extension_loaded($e)) exit(1); }'
COPY --from=composer@sha256:511d2a96fce74de098b54224d59146fb8b6a7735d4e301f16c87c062e511900d /usr/bin/composer /usr/local/bin/composer
# Use the fixed Composer image's BusyBox unzip with its musl loader; no apt repositories.
COPY --from=composer@sha256:511d2a96fce74de098b54224d59146fb8b6a7735d4e301f16c87c062e511900d /bin/busybox /usr/local/bin/busybox-musl
COPY --from=composer@sha256:511d2a96fce74de098b54224d59146fb8b6a7735d4e301f16c87c062e511900d /lib/ld-musl-x86_64.so.1 /lib/ld-musl-x86_64.so.1
RUN ln -s /usr/local/bin/busybox-musl /usr/local/bin/unzip \
    && /usr/local/bin/busybox-musl --list | grep -qx unzip \
    && probe="$(mktemp -d)" \
    && php -r 'file_put_contents($argv[1], base64_decode("UEsDBBQAAAAIAIgyeV1XAsRBCwAAAAkAAAAJAAAAcHJvYmUudHh0K67MK8lILclMBgBQSwECFAAUAAAACACIMnldVwLEQQsAAAAJAAAACQAAAAAAAAAAAAAAgAEAAAAAcHJvYmUudHh0UEsFBgAAAAABAAEANwAAADIAAAAAAA==", true));' "$probe/probe.zip" \
    && unzip -qq -o "$probe/probe.zip" -d "$probe/out" \
    && test "$(cat "$probe/out/probe.txt")" = synthetic \
    && rm -rf -- "$probe"
WORKDIR /workspace
