# Subversion Migration

This code was originally imported from SourceForge, using the
[svn2git](https://github.com/svn-all-fast-export/svn2git) tool.

### Subversion extraction

```bash
rsync -avP svn.code.sf.net::p/phpgiftreg/code svnroot
```

### Run svn2git

```bash
svn-all-fast-export --identity-map ./authors.txt --empty-dirs --rules ./phpgiftreg.rules svnroot/code
```

### Repack and tag branches

```bash
cd phpgiftreg

git repack -a -d -f

git tag v2.0.0 phpgiftreg-2.0.0
git tag v2.1.0 phpgiftreg-2.1.0
git tag v2.1.1 phpgiftreg-2.1.1
```

