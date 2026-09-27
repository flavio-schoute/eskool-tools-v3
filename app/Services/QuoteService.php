<?php

declare(strict_types=1);

namespace App\Services;

final class QuoteService
{
    /**
     * @return array{text: string, author: string}
     */
    public static function dailyQuote(): array
    {
        $quotes = [
            ['text' => 'Laravel is a web application framework with expressive, elegant syntax.', 'author' => 'Taylor Otwell'],
            ['text' => 'Code is like humor. When you have to explain it, it\'s bad.', 'author' => 'Cory House'],
            ['text' => 'First, solve the problem. Then, write the code.', 'author' => 'John Johnson'],
            ['text' => 'Any fool can write code that a computer can understand. Good programmers write code that humans can understand.', 'author' => 'Martin Fowler'],
            ['text' => 'Simplicity is the soul of efficiency.', 'author' => 'Austin Freeman'],
            ['text' => 'Make it work, make it right, make it fast.', 'author' => 'Kent Beck'],
            ['text' => 'The best error message is the one that never shows up.', 'author' => 'Thomas Fuchs'],
            ['text' => 'Clean code always looks like it was written by someone who cares.', 'author' => 'Robert C. Martin'],
            ['text' => 'Programming is the art of algorithm design and the craft of debugging errant code.', 'author' => 'Ellen Ullman'],
            ['text' => 'Don\'t comment bad code — rewrite it.', 'author' => 'Brian W. Kernighan'],
            ['text' => 'Every great developer you know got there by solving problems they were unqualified to solve.', 'author' => 'Patrick McKenzie'],
            ['text' => 'Software is a great combination of artistry and engineering.', 'author' => 'Bill Gates'],
            ['text' => 'The most important property of a program is whether it accomplishes the intention of its user.', 'author' => 'C.A.R. Hoare'],
            ['text' => 'Optimism is an occupational hazard of programming; feedback is the treatment.', 'author' => 'Kent Beck'],
            ['text' => 'It\'s not a bug — it\'s an undocumented feature.', 'author' => 'Anonymous'],
            ['text' => 'The function of good software is to make the complex appear to be simple.', 'author' => 'Grady Booch'],
            ['text' => 'Code never lies, comments sometimes do.', 'author' => 'Ron Jeffries'],
            ['text' => 'Walking on water and developing software from a specification are easy if both are frozen.', 'author' => 'Edward V. Berard'],
            ['text' => 'The best way to predict the future is to implement it.', 'author' => 'David Heinemeier Hansson'],
            ['text' => 'Laravel is not just a framework, it\'s a philosophy of elegant, simple solutions.', 'author' => 'Taylor Otwell'],
            ['text' => 'Artisan is your best friend. Learn its commands and it will save you hours.', 'author' => 'Laravel Community'],
            ['text' => 'Eloquent makes database interaction feel like speaking your native language.', 'author' => 'Laravel Docs'],
            ['text' => 'Migrations are version control for your database. Treat them with the same respect.', 'author' => 'Laravel Community'],
            ['text' => 'A well-tested application is a confident application.', 'author' => 'Laravel Community'],
            ['text' => 'Blade makes your views clean, but your logic should live in your controllers.', 'author' => 'Laravel Best Practices'],
            ['text' => 'Simple, fast, reliable. Pick three — with Laravel, you get all of them.', 'author' => 'Laravel Community'],
            ['text' => 'Test driven development gives you courage to refactor.', 'author' => 'Robert C. Martin'],
            ['text' => 'The goal of software architecture is to minimize the human resources required to build and maintain the required system.', 'author' => 'Robert C. Martin'],
            ['text' => 'Premature optimization is the root of all evil.', 'author' => 'Donald Knuth'],
            ['text' => 'Programs must be written for people to read, and only incidentally for machines to execute.', 'author' => 'Harold Abelson'],
        ];

        $index = (int) date('z') % count($quotes);

        return $quotes[$index];
    }
}
