We welcome and encourage contributions to the `WindAndKite_Storyblok` Magento 2 module! Whether you're reporting a bug, suggesting a new feature, improving documentation, or submitting code, your efforts help make this module better for everyone.

This guide outlines the process for contributing to the project.

## 🐞 Bug Reports

If you discover a bug, please help us by reporting it. A good bug report is crucial for a quick resolution.

1.  **Check Existing Issues:** Before opening a new issue, please check the [GitHub Issues](https://github.com/windandkite/storyblok/issues) to see if the bug has already been reported.

2.  **Open a New Issue:** If not, open a new issue and provide as much detail as possible:

    * **Module Version:** Specify the version of `WindAndKite_Storyblok` you are using.

    * **Magento Version:** State your Magento 2 version.

    * **PHP Version:** Include your PHP version.

    * **Steps to Reproduce:** Provide clear, concise steps to reproduce the bug.

    * **Expected Behavior:** Describe what you expected to happen.

    * **Actual Behavior:** Describe what actually happened, including any error messages from Magento logs (`var/log/system.log`, `var/log/exception.log`) or your browser console.

    * **Screenshots/Video:** Include screenshots or a short video if it helps illustrate the problem.

## ✨ Feature Requests & Ideas

Have an idea for a new feature or an improvement? We'd love to hear it!

1.  **Check Existing Discussions/Issues:** First, search the [GitHub Issues](https://github.com/windandkite/storyblok/issues) to see if your idea has already been proposed.

2.  **Open a New Issue:** If not, open a new discussion or issue, clearly outlining your proposal:

    * **Problem:** Describe the problem your feature aims to solve.

    * **Proposed Solution:** Explain how your feature would address the problem.

    * **Use Case:** Provide a real-world example of how this feature would be used.

    * **Benefits:** Explain the benefits of implementing this feature for other users.

## 🧑‍💻 Code Contributions (Pull Requests)

We welcome pull requests for bug fixes, new features, and improvements.

### Contribution Workflow

To contribute code, please follow these steps:

1.  **Fork the Repository:**
    Go to the `WindAndKite_Storyblok` [GitHub repository](https://github.com/windandkite/storyblok) and click the "Fork" button. This creates a copy of the repository under your GitHub account.

2.  **Clone Your Fork:**
    Clone your forked repository to your local development environment:
    ```bash
    git clone https://github.com/YourGitHubUser/storyblok.git
    cd storyblok
    ```

3.  **Install for Development:**
    If the `WindAndKite_Storyblok` module was previously installed in your Magento project via Composer, it's crucial to **delete its directory from `vendor/windandkite/storyblok`** first. Then, configure your Magento project to use your local source:
    * Add a [path repository](https://getcomposer.org/doc/05-repositories.md#path) to your Magento project's `composer.json` that points to your local fork.
    * Run `composer install --prefer-source` to install the module from your local source code.

4.  **Checkout the `master` Branch:**
    Ensure your local `master` branch is up-to-date with the original repository's `master` branch:
    ```bash
    git checkout master
    git pull upstream main # (If you've added 'upstream' remote, see next step)
    ```

5.  **Update Git Remote (Optional but Recommended):**
    To easily pull updates from the original repository and push to your fork, add the original repository as an "upstream" remote:
    ```bash
    git remote add upstream https://github.com/windandkite/storyblok
    ```
    You can then fetch and rebase/merge changes from `upstream/master` to keep your local `master` branch current.

6.  **Create a New Branch:**
    Create a new descriptive branch for your changes:
    ```bash
    git checkout -b feature/my-new-feature # for new features
    # or
    git checkout -b bugfix/fix-that-bug # for bug fixes
    ```

7.  **Make Your Changes:**
    Implement your code changes, ensuring they align with the proposed bug fix or feature.

9.  **Update Documentation:**
    If your changes affect functionality or configuration, update the relevant documentation pages within this wiki.

10. **Commit Your Changes:**
    Write clear, concise commit messages. Follow conventional commits if possible (e.g., `feat: Add new feature`, `fix: Resolve bug`).

11. **Push Your Branch to Your Fork:**
    ```bash
    git push origin feature/my-new-feature # Replace 'feature/my-new-feature' with your branch name
    ```

12. **Create a Pull Request:**
    Go to your forked repository on GitHub. You should see a prompt to "Compare & pull request" from your new branch to the `master` branch of the original `WindAndKite_Storyblok` repository.
    * Provide a clear title and detailed description for your PR.
    * Reference any related issues (e.g., `Fixes #123`, `Closes #456`).
    * Include screenshots or GIFs if the changes involve UI.

### Coding Standards

Please adhere to the [Magento Coding Standard](https://developer.adobe.com/commerce/php/coding-standards/) (PSR-1, PSR-2, and Magento-specific rules). This helps maintain code consistency and readability.

### Code Review

Your pull request will be reviewed by the maintainers. Be prepared to address feedback and make further changes. Once approved, your changes will be merged into the `master` branch!

## 🤝 Thank you
Thank you for contributing to the `WindAndKite_Storyblok` module! Your efforts are greatly appreciated.
