<?php

namespace App\Controller\Admin;

use App\Entity\Director;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;

class DirectorsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Director::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle('index','Directors');
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            'id',
            'name',
            'birthdate',
            AssociationField::new('films')->setTemplatePath('admin/field/films.html.twig'),
        ];

        if ($pageName === 'edit') {
            $fields = [
                Field::new('id')->setDisabled(true),
                'name',
                'birthdate',
            ];
        }

        return $fields;
    }

}
