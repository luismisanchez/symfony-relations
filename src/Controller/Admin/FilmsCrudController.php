<?php

namespace App\Controller\Admin;

use App\Entity\Film;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class FilmsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Film::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index','Films')
            ->setSearchFields(['title', 'genre', 'productionCompany','actors.name','director.name'])
            ->setDefaultSort(['title' => 'ASC'])
            ->setPaginatorUseOutputWalkers(true)
            ->setPaginatorFetchJoinCollection(true);
    }

    /**
     * Disable Films CRUD operations
     *
     * @param Actions $actions
     * @return Actions
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('title')->setTemplatePath('admin/field/film.html.twig'),
            IntegerField::new('datePublished')
                ->formatValue(function ($value, $entity) {
                    return date('M d, Y', $value);
                }),
            'genre',
            IntegerField::new('duration')
                ->formatValue(function ($value, $entity) {
                    return $value . ' min.';
                }),
            'productionCompany',
            AssociationField::new('actors')->setTemplatePath('admin/field/actors.html.twig'),
            AssociationField::new('director')->setTemplatePath('admin/field/directors.html.twig')
        ];
    }
}
