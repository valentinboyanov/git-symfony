# Git Symfony – First Commit Recreation

This project re-implements the functionality that shipped with the very [first Git commit](https://github.com/git/git/commit/e83c5163316f89bfbde7d9ab23ca2e25604af290), but in PHP on top of Symfony Console and friends. It keeps the same command names (`init-db`, `update-cache`, `write-tree`, and so on), the same `.dircache` layout, and the same loose object format so you can reason about the original design while working in a modern stack.

## Requirements

- PHP 8.2+ with the `zlib` extension enabled.
- Composer.

Install dependencies:

```bash
composer install
```

## Walkthrough

The quickest way to see everything working is to follow the original workflow in a scratch repo.

1. **Initialize the object store**

   ```bash
   ./bin/git-symfony init-db
   ```

   This creates `.dircache` and its `objects` fan-out directories (or honors `SHA1_FILE_DIRECTORY` if you point it elsewhere).

2. **Add files to the cache**

   ```bash
   echo "hello" > hello.txt
   ./bin/git-symfony update-cache hello.txt
   ```

   `update-cache` validates the path, stores `hello.txt` as a blob object in `.dircache/objects`, and records its metadata in `.dircache/index`.

3. **Write a tree object**

   ```bash
   TREE=$(./bin/git-symfony write-tree)
   echo "Created tree $TREE"
   ```

   The command streams the index entries into a canonical tree object, checks that every referenced blob exists, and prints the resulting SHA-1.

4. **Inspect tree contents**

   ```bash
   ./bin/git-symfony read-tree "$TREE"
   ```

   Output mirrors the C version: `<mode> <path> (<sha1>)`.

5. **Create a commit**

   ```bash
   COMMIT=$(printf "Initial import\n" | ./bin/git-symfony commit-tree "$TREE")
   echo "Commit $COMMIT"
   ```

   `commit-tree` pulls author/committer information from `COMMITTER_*` environment variables or your system user entry and hashes the message + parents + tree pointer into a commit object.

6. **Diff working tree vs. cache**

   ```bash
   ./bin/git-symfony show-diff
   ```

   Any file whose stat information no longer matches the cache is piped through `diff -u - <file>` exactly like `show-diff.c`.

7. **Extract an object**

   ```bash
   ./bin/git-symfony cat-file "$COMMIT"
   ```

   Writes the inflated contents into a temporary file and prints its path/type, just like Linus’ original helper.

## Testing

Unit tests cover the critical plumbing (SHA-1 conversion, index parsing, object storage, etc.). Run them with:

```bash
./vendor/bin/phpunit
```

## Notes

- Every command works relative to the current working directory. If you want to experiment in another location, just `cd` there first.
- The implementation keeps the original `.dircache` naming, but nothing prevents you from wiring environment variables (e.g. `SHA1_FILE_DIRECTORY`) to share object stores between working directories.
