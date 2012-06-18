Overview
========
This directory contains Clang programs to do source-to-source transformation of
C and C++ code.

*   `remove-main.c` removes the `main()` function from the provided `.c` or
    `.cpp` file. Run as:

        $ remove-main.c <filename>

*   `rename.c` prepends a `'_'` to all of the non-system functions in the
    provided `.c`  or `.cpp` file.  If you run it with just a filename, it will
    rename all user-defined functions:

        $ rename <filename>

    You can also give it additional arguments to just rename particular
    functions:

        $ rename <filename> [function [function ...]]

Compilation
===========
To compile, you'll need the LLVM and the Clang development headers:

    $ sudo yum -y install clang-devel llvm-devel

The current Makefile is set to compile for Clang 2, since on the CS50 Appliance
v3.15, the latest version of Clang that we can get is Clang 2.9. If you're on
Fedora 17+, then you'll have Clang 3+ installed. You'll want to remove the
`-DCLANG_2` flag from the `CXXFLAGS` line in the Makefile.

Testing
=======
`foobar.c` and `foobar.h` are junk files in this directory for testing purposes.
They don't compile, but they do provide a bunch of different edge cases for
function declarations and such.